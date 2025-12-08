{* templates/partials/random_block.tpl *}
<div class="menu">
  <div class="m_best">
    <div class="m_foot">
      <div class="m_t" style="text-align: center; padding: 10px; color: white;">
        <div style="font-size: 12px; margin-top: 40px; margin-bottom: 12px;">
          Хочешь случайное число? Лови!
        </div>
        <div id="random-result" style="font-size: 14px; font-weight: bold;">Число: —</div>
        <button
          onclick="document.getElementById('random-result').innerText = 'Число: ' + Math.floor(Math.random() * 100 + 1);"
          style="margin-top: 10px; padding: 6px 12px; font-size: 12px; cursor: pointer;">
          Сгенерировать
        </button>
      </div>
    </div>
  </div>
</div>
