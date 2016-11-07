<?php
/**
 * inPlaceEditor snippet
 */

// работаем только если авторизованы в админке
if (!isset($_SESSION['mgrValidated'])) return;

if (!class_exists('phpQuery')) {
    require_once MODX_BASE_PATH . 'assets/snippets/inPlaceEditor/phpQuery/phpQuery/phpQuery.php';
}

$params = $modx->event->params;

if (isset($params['element'])) {
    $element = $params['element'];
} else {
    $element = false;
}

if (isset($params['elementName'])) {
    $elementName = $params['elementName'];
} else {
    $elementName = false;
}

if (!$elementName || !$element) return;

foreach ($params as $pName => $pVal) {
    if (substr($pName, 0, 5) == 'class') {
        $num = substr($pName, 5);
        $classes[$num] = $pVal;
    }
    if (substr($pName, 0, 6) == 'change') {
        $num = substr($pName, 6);
        $children[$num] = $pVal;
    }
    if (substr($pName, 0, 5) == 'label') {
        $num = substr($pName, 5);
        $labels[$num] = $pVal;
    }
    if (substr($pName, 0, 5) == 'input') {
        $num = substr($pName, 5);
        $inputs[$num] = $pVal;
    }
}

///////////////////////// ajax ответ
if (isset($_REQUEST['class']) && in_array(trim($_REQUEST['class']), $classes)) {
    $ret['status'] = 'OK';

    $class = trim($_REQUEST['class']);
    list($eId, $num) = explode('_', trim($_REQUEST['ind'])); 

    switch ($element) {
        case 'tpl':
            $res = $modx->db->select('*', $modx->getFullTableName('site_templates'), 'templatename="' . $modx->db->escape($elementName) . '"');
            $cnt = $modx->db->getRecordCount($res);
            if ($cnt < 1) {
                return;
            }
            $row = $modx->db->getRow($res);
            $code = $row['content'];
        break;

        case 'chunk':
            $res = $modx->db->select('*', $modx->getFullTableName('site_htmlsnippets'), 'name="' . $modx->db->escape($elementName) . '"');
            $cnt = $modx->db->getRecordCount($res);
            if ($cnt < 1) {
                return;
            }
            $row = $modx->db->getRow($res);
            $code = $row['snippet'];
        break;

        default:
            $ret['status'] = 'Bad';
            die(json_encode($ret));
        break;
    }

    // сохраняем в папке со сниппетом бекап
    $fName = date('d.m.Y_H:i') . '_' . $elementName . '_' . $element . '_bkp.txt';
    file_put_contents(MODX_BASE_PATH . 'assets/snippets/inPlaceEditor/' . $fName, $code);

    // замена всех modx фиговин на временные хеши
    $re = '~\{\{(.*)?\}\}|\[\[(.*)?\]\]|\[\!(.*)?\!\]|\[\+(.*)?\+\]|\[\*(.*)?\*\]~msU';
    preg_match_all($re, $code, $resAr);

    foreach ($resAr[0] as $modxEntity) {
        $hash = '<i class="' . md5($modxEntity) .'"></i>';
        $hAr[$hash] = $modxEntity;
        $code = str_replace($modxEntity, $hash, $code);
    }

    $doc = phpQuery::newDocument($code);
    $elmCollection = $doc->find('.' . $class);

    $i = 0;
    foreach ($elmCollection as $elm) {
        if ($i == $eId) {
            if (isset($children[$num]) && trim($children[$num]) != '') {
                // указан дочерний элемент
                $tmpAr = explode('|', $children[$num]);
                foreach ($tmpAr as $ind => $domSelector) {
                    $ret['val' . $ind] = trim($_REQUEST['v' . $ind]);
                    $subElm = pq($elm)->find($domSelector);
                    pq($subElm)->html($ret['val' . $ind]);
                }
            } else {
                // переписываем сам контент
                $ret['val0'] = trim($_REQUEST['v0']);
                pq($elm)->html(trim($_REQUEST['v0']));
                break;
            }
        }            
        $i++;
    }

    // после всех замен возвращаем modx сущности на место
    $code = $doc->html();
    foreach ($hAr as $hash => $modxEntity) {
        $code = str_replace($hash, $modxEntity, $code);
    }

    switch ($element) {
        case 'tpl':
            $fs['content'] = $modx->db->escape($code);
            $modx->db->update($fs, $modx->getFullTableName('site_templates'), 'templatename="' . $modx->db->escape($elementName) . '"');
        break;

        case 'chunk':
            $fs['snippet'] = $modx->db->escape($code);
            $modx->db->update($fs, $modx->getFullTableName('site_htmlsnippets'), 'name="' . $modx->db->escape($elementName) . '"');
        break;
    }

    // чистим кеш modx
    $modx->clearCache();

    die(json_encode($ret));
}

//////////////////////// сборка js
$jsStr = '<script>
$(function() {    
';

$jsAr = array();

foreach ($classes as $num => $class) {
    $targets = '';
    $vals = '';
    $fvals = '';
    $hides = '';
    $titles = '';
    $fields = '';
    $appends = '';
    $fills = '';
    $shows = '';
    $rets = '';

    if (!isset($labels[$num]) || trim($labels[$num]) == '') {
        $titles.= '
        var t0 = "Текст";';
    } else {
        $tmpAr = explode('|', $labels[$num]);
        foreach ($tmpAr as $i => $title) {
            $titles.= '
        var t' . $i . ' = "' . $title . '";';
        }
    }

    if (!isset($inputs[$num]) || trim($inputs[$num]) == '') {
        $fields.= '
        var inp0 = jQuery(\'<p><label>\' + t0 + \' <input type="text" id="eip-i0\' + ind + \'" /></label></p>\');';

        $appends.= '
        f.append(inp0);';

        $fills.= '
        jQuery(\'#eip-i0\' + ind).val(v0);';

        $fvals.= '
            fData.v0 = $(\'#eip-i0\' + ind).val();';

        $rets.= '
                    ch0.html(d.val0);';
    } else {
        $tmpAr = explode('|', $inputs[$num]);
        foreach ($tmpAr as $i => $field) {
            if ($field == 't') {
                $fields.= '
        var inp' . $i . ' = jQuery(\'<p><label>\' + t' . $i . ' + \' <input type="text" id="eip-i' . $i . '\' + ind + \'" /></label></p>\');';
            }
            if ($field == 'ta') {
                $fields.= '
        var inp' . $i . ' = jQuery(\'<p><label>\' + t' . $i . ' + \' <textarea id="eip-i' . $i . '\' + ind + \'"></textarea></label></p>\');';
            }

            $appends.= '
        f.append(inp' . $i . ');';

            $fills.= '
        jQuery(\'#eip-i' . $i . '\' + ind).val(v' . $i . ');';

            $fvals.= '
            fData.v' . $i . ' = $(\'#eip-i' . $i . '\' + ind).val();';

            $rets.= '
                    ch' . $i . '.html(d.val' . $i . ');
                    ch' . $i . '.show();';
        }
    }

    if (!isset($children[$num]) || trim($children[$num]) == '') {
        $targets.= '
        var ch0 = jQuery(this);';

        $vals = '
        var v0 = ch0.html();';

        // $hides.= '
        // ch0.hide();';
        $hides.= '
        ch0.html(\'\');';
        // $shows.= '
        //             ch0.show();';
        $shows.= '
            ch0.html(v0);';
    } else {
        $tmpAr = explode('|', $children[$num]);
        foreach ($tmpAr as $i => $domSelector) {
            $targets.= '
        var ch' . $i . ' = jQuery(this).find("' . $domSelector . '");';

            $vals.= '
        var v' . $i . ' = ch' . $i . '.html();';

            $hides.= '
        ch' . $i . '.hide();';
            $shows.= '
            ch' . $i . '.show();';

        }
    }

    $jsAr[$num] = <<<JS

    function resetLink{$num}() {
        jQuery('.{$class} a').on('click', function(e) {
            e.stopPropagation();
            return false;
        });
    }

    resetLink{$num}();

    jQuery('.{$class}').on('dblclick', function() {
        var me = jQuery(this);
        if (me.hasClass('edited')) {
            return;
        } else {
            me.addClass('edited');
        }
        var ind = jQuery('.{$class}').index(me) + '_{$num}';

        {$targets}
        {$vals}
        {$titles}
        {$hides}

        var f = jQuery('<form id="edit-in-place' + ind + '"></form>');
        var bSave = jQuery('<button id="edit-in-place-save' + ind + '">Сохранить</button>');
        var bCancel = jQuery('<button id="edit-in-place-cancel' + ind + '">Отмена</button>');

        {$fields}

        
        jQuery(this).append(f);
        {$appends}
        {$fills}
        f.append(bSave);
        f.append(bCancel);

        jQuery('#edit-in-place-cancel' + ind).on('click', function() {
            f.detach();
            {$shows}
            me.removeClass('edited');
            resetLink{$num}();
        });

        jQuery('#edit-in-place-save' + ind).on('click', function() {
            var fData = {};
            fData.class = '{$class}';
            fData.ind =  ind;
            {$fvals}
            jQuery.post(document.location, fData, function(d) {
                console.log(d);
                if (typeof undefined == typeof d.status) {
                    alert('Ошибка сохранения');
                } else {
                    if (typeof undefined != typeof d.mess) {
                        alert(d.mess);
                    }

                    f.detach();
                    {$rets}
                    me.removeClass('edited');
                    resetLink{$num}();
                }
            }, 'json')
            .fail(function() {
                alert('Ошибка сети');
            });
            
            return false;
        });
        
    });
JS;
}

$jsStr.= implode("\n", $jsAr);

$jsStr.= '
});    
</script>';

$modx->setPlaceholder('eip.scripts', $jsStr);