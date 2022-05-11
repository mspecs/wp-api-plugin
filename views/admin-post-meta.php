<?php

$metas = mspecs_get_mspecs_meta(get_post());

if(empty($metas) || !is_array($metas)): ?>
    <p class="mspecs-no-data">
        <?= __('No data available', 'mspecs'); ?>
    </p>
<?php else:
    ksort($metas);
?>
    <table class="mspecs-meta-table">
        <?php foreach($metas as $meta_key => $value):
            $is_json = !is_string($value);
            $value = is_string($value) ? $value : json_encode($value, JSON_PRETTY_PRINT);
            ?>
            <tr>
                <th><?= esc_html($meta_key); ?></th>
                <?php if($is_json): ?>
                    <td><pre><?= esc_html($value); ?></pre></td>
                <?php else: ?>
                    <td><?= esc_html($value); ?></td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif ?>