/*global dotclear */
'use strict';

dotclear.ready(() => {
  const data = dotclear.getData('spamplemousse2');
  const reset = document.querySelector('[name="s2_reset"]');
  if (reset) {
    reset.addEventListener('click', (event) => dotclear.confirm(data.msg_reset, event));
  }
});
