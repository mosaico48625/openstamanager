<?php

include_once __DIR__.'/../../../core.php';

$rs = $dbo->fetchArray('SELECT id, descrizione, qta, threshold_qta, um AS unitamisura FROM mg_articoli WHERE qta < threshold_qta AND attivo = 1 ORDER BY qta ASC');

if (!empty($rs)) {
    echo '
<table class="table table-hover table-striped">
    <tr>
        <th width="80%">'._('Articolo').'</th>
        <th width="20%">'._('Q.tà').'</th>
    </tr>';

    foreach($rs as $r){
        echo '
    <tr>
        <td>
            '.Modules::link('Articoli', $r['id'], $r['descrizione']).'
        </td>
        <td>
            '.$r['qta'].' '.$r['unitamisura'].'
        </td>
    </tr>';
    }

    echo '
</table>';
} else {
    echo '<p>'._('Non ci sono articoli in esaurimento').".</p>\n";
}