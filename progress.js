/*global $ */
/*export progressUpdate */
"use strict";

function progressUpdate(funcClass, funcMethod, pos, start, stop, baseInc, nonce) {
  const params = {
    f: 'postProgress',
    funcClass,
    funcMethod,
    pos,
    start,
    stop,
    baseInc,
    total_elapsed: 0,
    xd_check: nonce,
  };
  $.post('services.php', params, (data) => {
    update(data);
  });
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
  $('#eta').append(`${eta} s`);

  const params = {
    f: 'postProgress',
    pos: $(data).find('pos').text(),
    total_elapsed: $(data).find('total_elapsed').text(),
    start: $(data).find('start').text(),
    stop: $(data).find('stop').text(),
    baseInc: $(data).find('baseinc').text(),
    funcClass: $(data).find('funcClass').text(),
    funcMethod: $(data).find('funcMethod').text(),
    xd_check: $(data).find('nonce').text()
  };

  $.post('services.php', params, (data) => {
    update(data);
  });
}
