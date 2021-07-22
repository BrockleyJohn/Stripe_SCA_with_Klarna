<?php
/* 
  reuse methods for configuration extensions across core module hierarchies
  more on: cartmart.uk
  author: John Ferguson @BrockleyJohn oscommerce@sewebsites.net
  date: Feb 2021
  copyright (c) SEwebsites 2021
  released under MIT licence without warranty express or implied
*/

trait cartmart_cfg_modals {
  
  protected static function modal_button($txt, $tgt, $icon = null)
  {
    return tep_draw_bootstrap_button($txt, $icon, null, null, ['type' => 'button', 'params' => 'data-toggle="modal" data-target="#' . $tgt . '"'], 'btn-config btn-warning mr-2');
  }

  protected static function modal_ajax_onload($id, $onload, $text_title = null, $text_close = null)
  {
    if (is_null($text_title)) $hdr =  ''; 
    else $hdr = <<<EOH
        <div class="modal-header">
          <h5 class="modal-title" id="ajaxModalLabel">{$text_title}</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
EOH;
    $ftr = is_null($text_close) ? '' : <<<EOB
        <div class="modal-footer">
          <div class="container-fluid buttonSet text-right">
            <button type="button" class="close" data-dismiss="modal" aria-label="$text_close">$text_close</button>
          </div>
        </div>
EOB;
    $GLOBALS['footer_scripts'][] = <<<EOD
<div class="modal fade" id="{$id}" data-backdrop="static" data-keyboard="false" tabindex="-1">
  <div class="modal-dialog modal-lg">
$hdr
    <div class="modal-content">
      <div class="modal-body ajax-message">
      </div>
      <div class="modal-body m-ajax text-center a-loading">
        <span class="fa fa-spinner fa-spin fa-3x"></span>
      </div>
      <div class="modal-body m-ajax text-center a-success">
        <span class="fa fa-check-circle fa-3x text-success"></span>
      </div>
      <div class="modal-body m-ajax text-center a-failure">
        <span class="fa fa-times-circle fa-3x text-danger"></span>
      </div>
$ftr
    </div>
  </div>
</div>
<script>
  $('#{$id}').on('shown.bs.modal', $onload);
</script>
EOD;

  }

  protected static function ajax_onload_script($name, $url, $params = [], $msg = '') 
  {
    $params = json_encode($params);
    $GLOBALS['footer_scripts'][] = <<<EOD
<script>
function $name() {
  $.ajax({
    beforeSend : function() {
        $('.modal-body.ajax-message').append('<br>' + '{$msg}');
        $('.modal-body.m-ajax').hide();
        $('.modal-body.m-ajax.a-loading').show();
    },
    type: "POST",
    dataType: "html", 
    url: "{$url}",
    data: {$params},
    success: function (data) {
      $('.modal-body.m-ajax').hide();
      try {
        var json = $.parseJSON(data);
        $('.modal-body.ajax-message').append('<br>' + json.msg);
        if (json.status == 'ok') {
          $('.modal-body.m-ajax.a-success').show();
        } else {
          $('.modal-body.ajax-message').append(json.error);
          $('.modal-body.m-ajax.a-failure').show();
        }
      } catch (e) {
        console.log(e + ' ' + data);
        $('.modal-body.ajax-message').append(data);
        $('.modal-body.m-ajax').hide();
        $('.modal-body.m-ajax.a-failure').show();
      }
    },
    error: function (data) {
      $('.modal-body.ajax-message').append(data);
      $('.modal-body.m-ajax').hide();
      $('.modal-body.m-ajax.a-failure').show();
    }
  })
}
</script>
EOD;
  }
  
  protected static function modal_dialog($id, $content, $dia_title = null, $onsubmit = null, $form = false, $onload = null)
  {
    static $i = 0;
    $buttons = $form_in = $form_out = '';
    if ($form) {
      $formid = 'cfgModal' . $i;
      $form_in = tep_draw_form('cfg_modal', '#', null, 'post', 'id="' . $formid . '"');
      $form_out = '</form>';
      $buttons = tep_draw_bootstrap_button('OK', 'fas fa-check', null, null, null, 'btn-config btn-success mr-2') . '<button type="button" class="btn btn-light" data-dismiss="modal" aria-label="' . IMAGE_CANCEL . '">' . IMAGE_CANCEL . '</button>';
    }
    
    $GLOBALS['footer_scripts'][] = <<<EOD
<div class="modal fade" id="{$id}" tabindex="-1" aria-labelledby="{$id}Label" aria-hidden="true">
  {$form_in}
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="{$id}Label">{$dia_title}</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        {$content}
      </div>
      <div class="modal-footer">
        {$buttons}
      </div>
    </div>
  </div>
  {$form_out}
</div>
EOD;

    if ($form && $onsubmit) {
      if ($onsubmit == 'modalupdate') {
        $script = <<<EOS
  const textFields = document.querySelectorAll('#{$formid} .modaltext');
  var toUpdate;
  for (var i = 0; i < textFields.length; i++) {
    toUpdate = document.querySelector('input[name="' + textFields[i].dataset.fieldtgt + '"]');
    toUpdate.value = textFields[i].value;
  }
  var checks = [];
  const checkFields = document.querySelectorAll('#{$formid} .modalchecks');
  for (i = 0; i < checkFields.length; i++) {
    if (checkFields[i].checked) {
      var tgt = checkFields[i].dataset.fieldtgt;
      if (tgt in checks) {
        checks[tgt] += ','+checkFields[i].value
      } else {
        checks[tgt] = ','+checkFields[i].value
      }
    }
  }
  for (var k in checks) {
    toUpdate = document.querySelector('input[name="' + k + '"]');
    toUpdate.value = checks[k].substring(1);
  }
EOS;
      }
      $GLOBALS['footer_scripts'][] = <<<EOD
<script>
var mForm = document.querySelector('#{$formid}');
mForm.onsubmit = function(e) {
  {$script}
  e.preventDefault();
  $('#{$id}').modal('hide');
  return false;
}
</script>
EOD;
    }
    $i++;
  }
}
