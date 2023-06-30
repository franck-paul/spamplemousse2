/*global $, dotclear */
/*export progressUpdate */
'use strict';

function progressUpdate(funcClass, funcMethod, pos, start, stop, baseInc) {
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
      total_elapsed: 0,
    },
  );
}

function humanReadableTime(elapsed) {
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
}

function update(data) {
  if ($(data).find('rsp').attr('status') != 'ok') {
    return;
  }

  if ($(data).find('error').length != 0) {
    window.alert('error: %s', $(data).find('error').text());
    return;
  }

  if ($(data).find('return').length != 0) {
    $('#return').show();
    $('#next').hide();
    return;
  }

  const percent = $(data).find('percent').text();
  $('#percent').empty();
  $('#percent').append(percent);

  const eta = $(data).find('eta').text();

  $('#eta').empty();
  $('#eta').append(humanReadableTime(eta));

  dotclear.servicesPost(
    'postProgress',
    (data) => {
      const xml = new DOMParser().parseFromString(data, 'text/xml');
      update(xml);
    },
    {
      funcClass: $(data).find('funcClass').text(),
      funcMethod: $(data).find('funcMethod').text(),
      pos: $(data).find('pos').text(),
      start: $(data).find('start').text(),
      stop: $(data).find('stop').text(),
      baseInc: $(data).find('baseinc').text(),
      total_elapsed: $(data).find('total_elapsed').text(),
    },
  );
}
