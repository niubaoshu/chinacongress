<?php
/*
Plugin Name: CC Assets
Description: 前端資產載入器:文章內容含 <!-- cc:assets --> 註釋記號即載入子主題 main.js,版本參數自動取檔案 mtime,免手動 bump。記號用 HTML 註釋而非 shortcode,因 og:description/生成文本等旁路直接讀原始內容,shortcode 原文會洩漏。
Version: 1.1.0
Author: China Congress 網絡部
*/
if (!defined('ABSPATH')) { exit; }

// 全資產(js/*.js + css/*.css)最大 mtime,作為子模組與 CSS 的快取版本值
function cc_assets_version() {
    $base = get_stylesheet_directory();
    $files = array_merge(
        glob($base . '/js/*.js') ?: array(),
        glob($base . '/css/*.css') ?: array()
    );
    $max = 0;
    foreach ($files as $f) {
        $t = @filemtime($f);
        if ($t !== false && $t > $max) { $max = $t; }
    }
    return $max;
}

function cc_assets_enqueue() {
    if (wp_script_is('cc-main', 'enqueued')) { return; }
    if (!is_singular()) { return; }
    $post = get_post();
    if (!$post || !preg_match('/<!--\s*cc:assets\s*-->/', $post->post_content)) { return; }
    $main = get_stylesheet_directory() . '/js/main.js';
    if (!file_exists($main)) { return; }
    wp_enqueue_script(
        'cc-main',
        get_stylesheet_directory_uri() . '/js/main.js',
        array(),
        (string) filemtime($main),
        true // footer:文章內嵌的 resp 永遠先於 main.js 執行
    );
    wp_add_inline_script(
        'cc-main',
        'window.cc_assets_ver="' . cc_assets_version() . '";',
        'before'
    );
}
add_action('wp_enqueue_scripts', 'cc_assets_enqueue');
