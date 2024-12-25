/*global $, dotclear, progressUpdate */
'use strict';

dotclear.ready(() => {
  $('#next').hide();
  const data = dotclear.getData('spamplemousse2');
  progressUpdate(data.funcClass, data.funcMethod, data.pos, data.start, data.stop, data.baseInc);
});
