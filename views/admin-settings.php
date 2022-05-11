<?php
if(!current_user_can(mspecs_admin_capability())){
    wp_die(__('You do not have sufficient permissions to access this page.'));
}
?>

<div class="wrap">
    <h1>Mspecs</h1>
    <form method="post" action="options.php">
        <?php
        settings_fields('mspecs');
        do_settings_sections('mspecs');
        submit_button();
        ?>
    </form>
    <div class="mspecs-actions-wrapper">
        <h2><?= __('Actions', 'mspecs') ?></h2>

        <?php
        $actions = Mspecs_Admin::get_admin_actions();
        foreach($actions as $id => $action): ?>
            <button type="button" class="button button-secondary" data-action="<?= esc_attr($id) ?>" data-nonce="<?= esc_attr(wp_create_nonce('mspecs-action-'.$id)) ?>">
                <?= esc_html($action['label']) ?>
            </button>
        <?php endforeach; ?>
    </div>
</div>