<?php
/*
Plugin Name: monospace ART - Sticky Post Filter
Description: Live filter posts below a sticky filter post by category, tag, custom taxonomies, and dynamic availability. Requires monospace ART Astra Child Theme.
Version: 1.0
Author: Hens Breet
*/

if(!defined('ABSPATH')) exit;

// ----------------------------
// 1. Shortcode for filter form
// ----------------------------
add_shortcode('monospace_post_filter', 'monospace_post_filter_form_shortcode');
function monospace_post_filter_form_shortcode(){
    return monospace_post_filter_form();
}

function monospace_post_filter_form(){
    $html = '<form method="get" class="ms-post-filter-form">';

    // Categories
    $categories = get_categories();
    $html .= '<select name="category"><option value="">All Categories</option>';
    foreach($categories as $cat){
        $selected = selected($_GET['category']??'',$cat->slug,false);
        $html .= "<option value='{$cat->slug}' {$selected}>{$cat->name}</option>";
    }

    $html .= '</select> ';

    // Tags
    $tags = get_tags();
    $html .= '<select name="tag"><option value="">All Tags</option>';
    foreach($tags as $tag){
        $selected = selected($_GET['tag']??'',$tag->slug,false);
        $html .= "<option value='{$tag->slug}' {$selected}>{$tag->name}</option>";
    }
    $html .= '</select> ';

    // Custom taxonomies
    $custom_taxonomies=['pa_medium','pa_size'];
    foreach($custom_taxonomies as $tax){
        $terms = get_terms(['taxonomy'=>$tax,'hide_empty'=>true]);
        if($terms && !is_wp_error($terms)){
            $label = ucfirst(str_replace('pa_','',$tax));
            $html .= "<select name='{$tax}'><option value=''>All {$label}</option>";
            foreach($terms as $term){
                $selected = selected($_GET[$tax]??'',$term->slug,false);
                $html .= "<option value='{$term->slug}' {$selected}>{$term->name}</option>";
            }
            $html .= '</select> ';
        }
    }

    // Availability
    $availabilities=['Available','Sold','On Hold','Coming Soon','Unavailable'];
    $html .= '<select name="availability"><option value="">Any Availability</option>';
    foreach($availabilities as $avail){
        $selected = selected($_GET['availability']??'',$avail,false);
        $html .= "<option value='{$avail}' {$selected}>{$avail}</option>";
    }

    $html .= ' <button type="submit">Filter</button></form>';
    return $html;
}

// ----------------------------
// 2. Enqueue JS for AJAX
// ----------------------------
add_action('wp_enqueue_scripts',function(){
    wp_enqueue_script('ms-post-filter',plugin_dir_url(__FILE__).'product-post-filter.js',['jquery'],'1.0',true);
    wp_localize_script('ms-post-filter','msPostFilter',['ajaxurl'=>admin_url('admin-ajax.php')]);
});

// ----------------------------
// 3. AJAX handler
// ----------------------------
add_action('wp_ajax_ms_filter_posts','ms_filter_posts_callback');
add_action('wp_ajax_nopriv_ms_filter_posts','ms_filter_posts_callback');

function ms_filter_posts_callback(){
    // Exclude sticky posts (filter post) from results
    $sticky_posts = get_option('sticky_posts') ?: [];

    $tax_query=[];
    if(!empty($_GET['category'])) $tax_query[]=['taxonomy'=>'category','field'=>'slug','terms'=>sanitize_text_field($_GET['category'])];
    if(!empty($_GET['tag'])) $tax_query[]=['taxonomy'=>'post_tag','field'=>'slug','terms'=>sanitize_text_field($_GET['tag'])];
    foreach(['pa_medium','pa_size'] as $tax){
        if(!empty($_GET[$tax])) $tax_query[]=['taxonomy'=>$tax,'field'=>'slug','terms'=>sanitize_text_field($_GET[$tax])];
    }

    $args=[
        'post_type'=>'post',
        'posts_per_page'=>10,
        'post__not_in'=>$sticky_posts,
        'tax_query'=>$tax_query?:[],
    ];

    $posts=get_posts($args);
    $avail=$_GET['availability']??'';

    if($avail){
        $filtered=[];
        foreach($posts as $post){
            $content = $post->post_content;
            $status='Unavailable';
            if(preg_match('/painting_buy_button\s+id=["\']?(\d+)["\']?\]/i',$content,$m)){
                $product_id=intval($m[1]);
                if(function_exists('wc_get_product')){
                    $product=wc_get_product($product_id);
                    if($product){
                        if($product->is_in_stock()) $status='Available';
                        elseif($product->get_stock_status()==='onbackorder') $status='On Hold';
                        elseif($product->get_stock_status()==='outofstock') $status='Sold';
                        else $status='Unavailable';
                        $price=$product->get_price();
                        if(empty($price)||floatval($price)===0) $status='Coming Soon';
                    }
                }
            }
            if($status===$avail) $filtered[]=$post;
        }
        $posts=$filtered;
    }

    if($posts){
        foreach($posts as $post){
            setup_postdata($post);
            echo '<div class="ms-post" style="margin-bottom:1em;">';
            if(preg_match('/<img[^>]+>/i',$post->post_content,$img)){
                echo '<div style="position:relative;">'.$img[0];
                // Availability badge
                $status='Unavailable';
                if(preg_match('/painting_buy_button\s+id=["\']?(\d+)["\']?\]/i',$post->post_content,$m)){
                    $product_id=intval($m[1]);
                    if(function_exists('wc_get_product')){
                        $product=wc_get_product($product_id);
                        if($product){
                            if($product->is_in_stock()) $status='Available';
                            elseif($product->get_stock_status()==='onbackorder') $status='On Hold';
                            elseif($product->get_stock_status()==='outofstock') $status='Sold';
                            else $status='Unavailable';
                            $price=$product->get_price();
                            if(empty($price)||floatval($price)===0) $status='Coming Soon';
                        }
                    }
                }
                echo '<div style="position:absolute;bottom:10px;left:10px;padding:4px 8px;background:rgba(0,0,0,0.6);color:white;font-size:12px;border-radius:4px;font-family:inherit;">'.esc_html($status).'</div>';
                echo '</div>';
            }
            echo '<a href="'.get_permalink($post).'">'.get_the_title($post).'</a>';
            echo '</div>';
        }
        wp_reset_postdata();
    } else echo '<p>No posts found.</p>';

    wp_die();
}
