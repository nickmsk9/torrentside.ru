/* ===== Fast cvi_filter (drop-in), API совместим с v1.6 ===== */
(function () {
  "use strict";

  // быстрый clamp через LUT
  var CLAMP = new Uint8ClampedArray(256 + 512);
  (function initClamp() {
    for (var i = -256, j = 0; i < 512; i++, j++) {
      CLAMP[j] = i < 0 ? 0 : (i > 255 ? 255 : i);
    }
  })();
  function clamp8(x) { return CLAMP[x + 256]; }

  // утилиты
  function getCtx(canvas) { return canvas && canvas.getContext ? canvas.getContext("2d") : null; }
  function toNumber(x, def) {
    var n = typeof x === "number" ? x : parseFloat(x);
    return isFinite(n) ? n : def;
  }
  function sumKernel3x3(k) {
    return k[0][0]+k[0][1]+k[0][2]+k[1][0]+k[1][1]+k[1][2]+k[2][0]+k[2][1]+k[2][2];
  }

  // ядерные таблицы для outline
  var EDGE = {
    sobel:  { y:[1,2,1, 0,0,0, -1,-2,-1], x:[1,0,-1, 2,0,-2, 1,0,-1] },
    scharr:{ y:[3,10,3, 0,0,0, -3,-10,-3], x:[3,0,-3, 10,0,-10, 3,0,-3] },
    prewitt:{ y:[-1,-1,-1, 0,0,0, 1,1,1], x:[1,0,-1, 1,0,-1, 1,0,-1] },
    kirsh: { y:[5,5,5, -3,0,-3, -3,-3,-3], x:[5,-3,-3, 5,0,-3, 5,-3,-3] },
    roberts:{ y:[-1,0,0, 0,1,0, 0,0,0], x:[0,0,-1, 0,1,0, 0,0,0] }
  };

  // свёртка 3x3 с зажимом координат по краю (edge clamp)
  function convolve3x3(src, dst, w, h, kernel, scale, bias) {
    var k00 = kernel[0][0], k01 = kernel[0][1], k02 = kernel[0][2];
    var k10 = kernel[1][0], k11 = kernel[1][1], k12 = kernel[1][2];
    var k20 = kernel[2][0], k21 = kernel[2][1], k22 = kernel[2][2];
    var s = (scale && scale[0] >= 0) ? scale[0] : (k00+k01+k02+k10+k11+k12+k20+k21+k22 || 1);
    var b = (scale && scale[1] >= 0) ? scale[1] : 0;

    for (var y = 0; y < h; y++) {
      var y0 = (y > 0 ? y - 1 : 0), y2 = (y < h - 1 ? y + 1 : h - 1);
      for (var x = 0; x < w; x++) {
        var x0 = (x > 0 ? x - 1 : 0), x2 = (x < w - 1 ? x + 1 : w - 1);

        var i00 = (y0 * w + x0) << 2, i01 = (y0 * w + x) << 2, i02 = (y0 * w + x2) << 2;
        var i10 = (y  * w + x0) << 2, i11 = (y  * w + x) << 2, i12 = (y  * w + x2) << 2;
        var i20 = (y2 * w + x0) << 2, i21 = (y2 * w + x) << 2, i22 = (y2 * w + x2) << 2;

        var r = (
          src[i00]*k00 + src[i01]*k01 + src[i02]*k02 +
          src[i10]*k10 + src[i11]*k11 + src[i12]*k12 +
          src[i20]*k20 + src[i21]*k21 + src[i22]*k22
        ) / s + b;

        var g = (
          src[i00+1]*k00 + src[i01+1]*k01 + src[i02+1]*k02 +
          src[i10+1]*k10 + src[i11+1]*k11 + src[i12+1]*k12 +
          src[i20+1]*k20 + src[i21+1]*k21 + src[i22+1]*k22
        ) / s + b;

        var bch = (
          src[i00+2]*k00 + src[i01+2]*k01 + src[i02+2]*k02 +
          src[i10+2]*k10 + src[i11+2]*k11 + src[i12+2]*k12 +
          src[i20+2]*k20 + src[i21+2]*k21 + src[i22+2]*k22
        ) / s + b;

        dst[i11]   = clamp8(r|0);
        dst[i11+1] = clamp8(g|0);
        dst[i11+2] = clamp8(bch|0);
        dst[i11+3] = src[i11+3];
      }
    }
  }

  // LUT для гаммы/постеризации и т.п.
  function gammaLUT(gamma) {
    var g = gamma > 0 ? gamma : 1;
    var t = new Uint8ClampedArray(256);
    var inv = 1 / g;
    for (var i = 0; i < 256; i++) {
      t[i] = clamp8(Math.round(255 * Math.pow(i / 255, inv)));
    }
    return t;
  }

  // быстрая яркость/контраст через LUT
  function contrastLUT(c) {
    // формула: (((x/255 - 0.5)*c)+0.5)*255
    var t = new Uint8ClampedArray(256);
    for (var i = 0; i < 256; i++) {
      t[i] = clamp8((((i / 255 - 0.5) * c) + 0.5) * 255);
    }
    return t;
  }
  function brightnessLUT(v) {
    var t = new Uint16Array(256);
    for (var i = 0; i < 256; i++) t[i] = Math.round(i * v);
    return t;
  }

  // === Drop-in совместимый объект ===
  window.cvi_filter = {
    version: 1.7,
    released: '2025-10-10 10:00:00',
    defaultF: null,
    defaultM: null,
    defaultS: -1,

    add: function (obj, img, opts, w, h) {
      if (!obj || obj.tagName.toUpperCase() !== "CANVAS") return false;

      var ctx = getCtx(obj);
      var bcx = getCtx(img); // буферный canvas (как в твоём коде)
      if (!ctx) return false;

      var def = { f: this.defaultF, m: this.defaultM, s: this.defaultS };
      opts = opts || def; for (var k in def) if (!(k in opts)) opts[k] = def[k];

      var f = typeof opts.f === "string" ? opts.f : null;
      var m = (typeof opts.m === "object" && opts.m) ? opts.m : null;
      var s = opts.s;

      // реальные размеры
      var W = Math.max(1, toNumber(w, obj.width));
      var H = Math.max(1, toNumber(h, obj.height));

      // защитное ограничение и подготовка
      var prepared = !!ctx.getImageData;
      if (!prepared || !f || W <= 0 || H <= 0) return false;

      // общие буферы
      var image = ctx.getImageData(0, 0, W, H);
      var a = image.data; // Uint8ClampedArray
      var out = ctx.createImageData(W, H);
      var b = out.data;

      // быстрые однопроходные фильтры
      if (f === "invert") {
        for (var i = 0, n = a.length; i < n; i += 4) {
          b[i]   = 255 - a[i];
          b[i+1] = 255 - a[i+1];
          b[i+2] = 255 - a[i+2];
          b[i+3] = a[i+3];
        }
        ctx.putImageData(out, 0, 0);
        return false;
      }

      if (f === "invertalpha") {
        for (var i2 = 0, n2 = a.length; i2 < n2; i2 += 4) {
          b[i2]   = a[i2];
          b[i2+1] = a[i2+1];
          b[i2+2] = a[i2+2];
          b[i2+3] = 255 - a[i2+3];
        }
        ctx.putImageData(out, 0, 0);
        return false;
      }

      if (f === "grayscale") {
        for (var i3 = 0, n3 = a.length; i3 < n3; i3 += 4) {
          var y = (a[i3] * 299 + a[i3+1] * 587 + a[i3+2] * 114 + 500) / 1000 | 0;
          b[i3] = b[i3+1] = b[i3+2] = y;
          b[i3+3] = a[i3+3];
        }
        ctx.putImageData(out, 0, 0);
        return false;
      }

      if (f === "threshold") {
        var thr = (typeof s === "number" ? Math.min(2, Math.max(0, s)) * 127 : 127) | 0;
        for (var i4 = 0, n4 = a.length; i4 < n4; i4 += 4) {
          var y4 = (a[i4] * 299 + a[i4+1] * 587 + a[i4+2] * 114 + 500) / 1000 | 0;
          var t = y4 >= thr ? 255 : 0;
          b[i4] = b[i4+1] = b[i4+2] = t;
          b[i4+3] = a[i4+3];
        }
        ctx.putImageData(out, 0, 0);
        return false;
      }

      if (f === "gamma") {
        var g = toNumber(s, 1);
        var lut = gammaLUT(g);
        for (var i5 = 0, n5 = a.length; i5 < n5; i5 += 4) {
          b[i5]   = lut[a[i5]];
          b[i5+1] = lut[a[i5+1]];
          b[i5+2] = lut[a[i5+2]];
          b[i5+3] = a[i5+3];
        }
        ctx.putImageData(out, 0, 0);
        return false;
      }

      if (f === "brightness") {
        var v = toNumber(s, 1);
        var blut = brightnessLUT(v);
        for (var i6 = 0, n6 = a.length; i6 < n6; i6 += 4) {
          b[i6]   = clamp8(blut[a[i6]]);
          b[i6+1] = clamp8(blut[a[i6+1]]);
          b[i6+2] = clamp8(blut[a[i6+2]]);
          b[i6+3] = a[i6+3];
        }
        ctx.putImageData(out, 0, 0);
        return false;
      }

      if (f === "contrast") {
        var cval = toNumber(s, 1);
        var clut = contrastLUT(cval);
        for (var i7 = 0, n7 = a.length; i7 < n7; i7 += 4) {
          b[i7]   = clut[a[i7]];
          b[i7+1] = clut[a[i7+1]];
          b[i7+2] = clut[a[i7+2]];
          b[i7+3] = a[i7+3];
        }
        ctx.putImageData(out, 0, 0);
        return false;
      }

      if (f === "sepia") {
        for (var i8 = 0, n8 = a.length; i8 < n8; i8 += 4) {
          var r = a[i8], g2 = a[i8+1], b2 = a[i8+2];
          b[i8]   = clamp8(r*0.393 + g2*0.769 + b2*0.189);
          b[i8+1] = clamp8(r*0.349 + g2*0.686 + b2*0.168);
          b[i8+2] = clamp8(r*0.272 + g2*0.534 + b2*0.131);
          b[i8+3] = a[i8+3];
        }
        ctx.putImageData(out, 0, 0);
        return false;
      }

      // контур (outline) — быстрый градиент по LUT ядрам
      if (f === "outline") {
        var vv = (Array.isArray(s) && s[0] >= 0) ? Math.min(255, s[0]) : 1;
        var bias = (Array.isArray(s) && s[1] >= 0) ? Math.min(255, s[1]) : 0;
        var type = (Array.isArray(s) && typeof s[2] === "string") ? s[2].toLowerCase() : "sobel";
        var ker = EDGE[type] || EDGE.sobel;

        // заранее подготовим яркость
        var Y = new Uint16Array(W * H);
        for (var y1 = 0, idx = 0; y1 < H; y1++) {
          for (var x1 = 0; x1 < W; x1++, idx++) {
            var i0 = idx << 2;
            Y[idx] = (a[i0]*299 + a[i0+1]*587 + a[i0+2]*114 + 500)/1000 | 0;
          }
        }

        for (var y2 = 0; y2 < H; y2++) {
          var y0 = (y2 > 0 ? y2 - 1 : 0), y3 = (y2 < H - 1 ? y2 + 1 : H - 1);
          for (var x2 = 0; x2 < W; x2++) {
            var x0 = (x2 > 0 ? x2 - 1 : 0), x3 = (x2 < W - 1 ? x2 + 1 : W - 1);
            var p00 = Y[y0*W + x0], p01 = Y[y0*W + x2], p02 = Y[y0*W + x3];
            var p10 = Y[y2*W + x0], p11 = Y[y2*W + x2], p12 = Y[y2*W + x3];
            var p20 = Y[y3*W + x0], p21 = Y[y3*W + x2], p22 = Y[y3*W + x3];

            var gy = ker.y[0]*p00 + ker.y[1]*p01 + ker.y[2]*p02 +
                     ker.y[3]*p10 + ker.y[4]*p11 + ker.y[5]*p12 +
                     ker.y[6]*p20 + ker.y[7]*p21 + ker.y[8]*p22;
            var gx = ker.x[0]*p00 + ker.x[1]*p01 + ker.x[2]*p02 +
                     ker.x[3]*p10 + ker.x[4]*p11 + ker.x[5]*p12 +
                     ker.x[6]*p20 + ker.x[7]*p21 + ker.x[8]*p22;

            var q = Math.min(255, Math.max(0, (Math.sqrt(gx*gx + gy*gy)/vv + bias) | 0));
            var oi = (y2 * W + x2) << 2;
            b[oi] = b[oi+1] = b[oi+2] = q;
            b[oi+3] = a[oi+3];
          }
        }
        ctx.putImageData(out, 0, 0);
        return false;
      }

      // свёртка 3x3 (convolve + имена из cvi_matrix)
      if (f === "convolve" || (f in window.cvi_matrix)) {
        var k = (f === "convolve") ? (m || window.cvi_matrix.blur) : window.cvi_matrix[f];
        var scale = Array.isArray(s) ? s : [this.defaultS, 0];
        // если scale не задан и сумма ядра 0 (например, edge-детекторы) — используем s=1
        if (!(scale && scale[0] >= 0)) {
          var sum = sumKernel3x3(k);
          scale = [sum || 1, (scale && scale[1] >= 0) ? scale[1] : 0];
        }
        convolve3x3(a, b, W, H, k, scale, scale[1]);
        ctx.putImageData(out, 0, 0);
        return false;
      }

      // ниже — редкие эффекты, слегка облегчённые (API не менял)
      if (f === "zoomblur" || f === "motionblur" || f === "spinblur" || f === "smooth") {
        if (!bcx) bcx = obj.getContext("2d"); // fallback
        var palpha = ctx.globalAlpha;
        if (f === "smooth") {
          var depth = Math.max(1, Math.min(10, toNumber(s, 1)));
          var passes = Math.round(depth * 5);
          var rw = Math.max(2, Math.round(W * 0.75)), rh = Math.max(2, Math.round(H * 0.75));
          for (var p = 0; p < passes; p++) {
            bcx.clearRect(0, 0, W, H);
            bcx.drawImage(obj, 0, 0, W, H, 0, 0, rw, rh);
            ctx.clearRect(0, 0, W, H);
            ctx.drawImage(img, 0, 0, rw, rh, 0, 0, W, H);
          }
        } else if (f === "zoomblur") {
          var v = Math.max(1, toNumber(s, 1));
          var base = 0.25, step = base / v;
          bcx.drawImage(obj, 0, 0, W, H, 0, 0, W, H);
          for (var i9 = 0; i9 < v; i9++) {
            ctx.globalAlpha = base - step * i9;
            ctx.drawImage(img, 0, 0, W, H, -i9, -i9, W + 2*i9, H + 2*i9);
          }
        } else if (f === "motionblur") {
          var len = (Array.isArray(s) && s[0] > 0) ? s[0] : 1;
          var ang = (Array.isArray(s) && s[1] >= 0) ? Math.min(360, s[1]) : 0;
          var base2 = 0.25, step2 = base2 / len;
          var rad = (ang - 90) * Math.PI / 180;
          var dx = Math.round(Math.cos(rad)), dy = Math.round(Math.sin(rad));
          bcx.drawImage(obj, 0, 0, W, H, 0, 0, W, H);
          var x = 0, y = 0;
          for (var i10 = 0; i10 < len; i10++) {
            x += dx; y += dy;
            ctx.globalAlpha = base2 - step2 * i10;
            ctx.drawImage(img, 0, 0, W, H, x, y, W, H);
          }
        } else if (f === "spinblur") {
          var turns = Math.max(1, toNumber(s, 1));
          var base3 = 0.25, step3 = base3 / turns;
          bcx.drawImage(obj, 0, 0, W, H, 0, 0, W, H);
          ctx.save(); ctx.translate(W/2, H/2);
          for (var i11 = 0; i11 < turns; i11++) {
            ctx.globalAlpha = base3 - step3 * i11;
            ctx.save(); ctx.rotate((Math.PI * i11)/180);
            ctx.drawImage(img, 0, 0, W, H, -W/2, -H/2, W, H);
            ctx.restore();
            ctx.save(); ctx.rotate((Math.PI * -i11)/180);
            ctx.drawImage(img, 0, 0, W, H, -W/2, -H/2, W, H);
            ctx.restore();
          }
          ctx.restore();
        }
        ctx.globalAlpha = palpha;
        return false;
      }

      // если фильтр не распознан — ничего не делаем (совместимое поведение)
      return false;
    }
  };
})();
