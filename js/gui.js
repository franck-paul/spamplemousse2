/*global $, dotclear, progressUpdate */
'use strict';

dotclear.ready(() => {
  const data = dotclear.getData('spamplemousse2');
  const reset = document.querySelector('[name="s2_reset"]');
  if (reset) {
    reset.addEventListener('click', (event) => {
      if (window.confirm(data.msg_reset)) return true;
      event.preventDefault();
      return false;
    });
  }
});
