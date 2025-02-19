/*global $, dotclear */
'use strict';

dotclear.ready(() => {
  // hide next button (not needed with JS)
  $('#next').hide();

  const data = dotclear.getData('spamplemousse2');

  const progressUpdate = (funcClass, funcMethod, pos, start, stop, baseInc, total_elapsed) => {
    dotclear.servicesPost(
      'postProgress',
      (data) => {
        const xml = new DOMParser().parseFromString(data, 'text/xml');
        update(xml);
      },
      {
        funcClass,
        funcMethod,
        pos,
        start,
        stop,
        baseInc,
        total_elapsed,
      },
    );
  };

  const humanReadableTime = (elapsed) => {
    const dateObj = new Date(elapsed * 1000);
    const hours = dateObj.getUTCHours();
    const minutes = dateObj.getUTCMinutes();
    const seconds = dateObj.getSeconds();
    let timeString = '';
    if (hours) {
      timeString += `${hours.toString().padStart(2, '0')}:`;
    }
    timeString += `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    return timeString;
  };

  const update = (data) => {
    if ($(data).find('rsp').attr('status') !== 'ok') {
      return;
    }

    if ($(data).find('error').length !== 0) {
      window.alert(`error: ${$(data).find('error').text()}`);
      return;
    }

    if ($(data).find('return').length !== 0) {
      $('#next').hide();
      return;
    }

    const percent = $(data).find('percent').text();
    $('#percent').empty();
    $('#percent').attr('value', percent);
    $('#percent').append(`${percent}/100`);

    const eta = $(data).find('eta').text();
    $('#eta').empty();
    $('#eta').append(humanReadableTime(eta));

    progressUpdate(
      $(data).find('funcClass').text(),
      $(data).find('funcMethod').text(),
      $(data).find('pos').text(),
      $(data).find('start').text(),
      $(data).find('stop').text(),
      $(data).find('baseinc').text(),
      $(data).find('total_elapsed').text(),
    );
  };

  progressUpdate(data.funcClass, data.funcMethod, data.pos, data.start, data.stop, data.baseInc, 0);
});
