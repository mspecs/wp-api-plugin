<?php
/*
 * Example PHP for displaying a single MSPECS deal, using ../public-api.php
 * 
 */

if(!defined('MSPECS_PLUGIN_DIR')) {
    echo 'MSPECS plugin not installed';
    return;
}

// Get an example deal ID, replace this with the ID you want to display
$deal_id = get_dummy_deal_id();

if(!$deal_id){
    echo 'No deals found, please configure plugin and sync all deals';
    return;
}

// Get the deal with the given ID, as a WP_Post object
$deal = mspecs_get_deal($deal_id);

// Get the deals meta data
$data = mspecs_get_mspecs_meta($deal);
?>

<?php // Output the selling text subject, using the safe array access method mspecs_get ?>
<h1><?= esc_html(mspecs_get($data, 'sellingTexts.sellingTextSubject')) ?></h1>

<?php // Output a formatted location, using the safe array access method mspecs_get ?>
<h3><?= esc_html(implode(', ', [mspecs_get($data, 'location.streetAddress'), mspecs_get($data, 'location.city')])) ?></h3>

<?php // Output the selling short text, using the safe array access method mspecs_get ?>
<p><?= esc_html(mspecs_get($data, 'sellingTexts.sellingTextShort')) ?></p>

<?php // Output the selling text, using the safe array access method mspecs_get ?>
<p><?= esc_html(mspecs_get($data, 'sellingTexts.sellingText')) ?></p>

<?php // Output images tags with descriptions, using the self hosted URL provided by mspecs_get_deal_images ?>
<?= implode('', array_map(function($image){
    return
        '<p>'
            .'<img src="'.esc_attr($image['viewUrl']).'" />'
            .'<em>'.esc_html($image['title']).'</em>'
        .'</p>';
}, mspecs_get_deal_images($deal))) ?>

<?php // Output file download links using the self hosted URL provided by mspecs_get_deal_files ?>
<ul><?= implode('', array_map(function($file){
    return '<li><a href="'.esc_url($file['url']).'" download>'.esc_html($file['title']).'</a></li>';
}, mspecs_get_deal_files($deal))) ?></ul>

<?php


/*
 * Ignore this function, it's just for the example
 */
function get_dummy_deal_id() {
    $deals = mspecs_get_deals();
    if(count($deals) > 0) {
        return mspecs_get_deal_meta('mspecs_id', $deals[0]);
    }
    return null;
}