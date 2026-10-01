/**
 * cc_video 兼容层
 * 全站短视频自动包装、相对路径补齐与互斥播放已统一由 functions.php 在 wp_footer 原生驱动，
 * 避免了多余的网络往返与内联样式冲突。本函数保留以兼容历史调用。
 */
function cc_video() {
    // 现代架构已由主题 footer 守护脚本统一托管
}
