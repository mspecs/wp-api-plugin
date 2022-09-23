<?php
/*
 * Example PHP for listing MSPECS deals, using ../public-api.php
 * 
 * Alternative method:
 *  - Deals are by default non-public custom post types.
 *    Using the 'mspecs_deal_post_type' filter you can instead make them public
 *    to utilize the standard WordPress API
 * 
 */

if(!defined('MSPECS_PLUGIN_DIR')) {
    echo 'MSPECS plugin not installed';
    return;
}

// Get all deals, as a WP_Post objects
$deals = mspecs_get_deals();

if(empty($deals)){
    echo 'No deals found, please configure plugin and sync all deals';
    return;
}

?>
<table>
    <?php
    // Loop through deals
    foreach($deals as $deal):

        // Get the deals meta data
        $data = mspecs_get_mspecs_meta($deal);

        ?>
        <tr>
            <?php // Output the shortId, using standard array access ?>
            <td><?= esc_html($data['shortId']) ?></td>

            <?php // Output a formatted location, using the safe array access method mspecs_get ?>
            <td><?= esc_html(implode(', ', [mspecs_get($data, 'location.streetAddress'), mspecs_get($data, 'location.city')])) ?></td>

            <?php // Output images tags using the self hosted URL provided by mspecs_get_deal_images ?>
            <td><?= implode('', array_map(function($image){
                return '<img src="'.esc_attr($image['thumbnailUrl']).'" />';
            }, mspecs_get_deal_images($deal))) ?></td>

            <?php // Output file download links using the self hosted URL provided by mspecs_get_deal_files ?>
            <td><?= implode('<br>', array_map(function($file){
                return '<a href="'.esc_url($file['url']).'" download>'.esc_html($file['title']).'</a>';
            }, mspecs_get_deal_files($deal))) ?></td>
        </tr>
    <?php endforeach ?>
</table>

