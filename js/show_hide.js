function show_hide(id) {
  var el = document.getElementById(id);
  if (!el) {
    return false;
  }

  var current = '';
  if (window.getComputedStyle) {
    current = window.getComputedStyle(el).display;
  } else if (el.currentStyle) {
    current = el.currentStyle.display;
  } else {
    current = el.style.display;
  }

  el.style.display = (current === 'none') ? '' : 'none';
  return false;
}
