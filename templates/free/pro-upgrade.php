<?php
/**
 * Central Pro upgrade card — included by tasksession_render_pro_upgrade().
 * Expects: $heading, $body, $buttonText, $featureName, $featureDescription, $upgradeUrl
 */
$h = static function ($v) {
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
};
$cssHref = (isset($url) ? rtrim((string) $url, '/') . '/' : '/') . 'assets/css/free-pro-upgrade.css?v=4';
?>
<link rel="stylesheet" href="<?php echo $h($cssHref); ?>">
<style>
/* Inline fallback so upgrade UI always centers even if CSS file is cached/blocked */
.ts-pro-upgrade-wrap {
    display: flex !important;
    justify-content: center !important;
    align-items: center !important;
    min-height: calc(100vh - 180px);
    width: 100% !important;
    max-width: 100% !important;
    margin: 0 !important;
    padding: 40px 20px 56px !important;
    box-sizing: border-box !important;
    background: transparent !important;
    border-radius: 0;
    text-align: center !important;
}
.ts-pro-upgrade-card {
    display: block !important;
    width: 100% !important;
    max-width: 440px !important;
    margin: 0 auto !important;
    padding: 40px 32px 36px !important;
    background: #ffffff !important;
    border: 1px solid #e1e5e9 !important;
    border-radius: 12px !important;
    box-shadow: 0 8px 28px rgba(34, 45, 50, 0.08) !important;
    text-align: center !important;
    box-sizing: border-box !important;
}
.ts-pro-upgrade-icon {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 64px !important;
    height: 64px !important;
    margin: 0 auto 18px !important;
    border-radius: 16px !important;
    background: #e8f4ff !important;
    color: #0094ff !important;
}
.ts-pro-upgrade-icon svg {
    display: block !important;
    margin: 0 auto !important;
    width: 30px !important;
    height: 30px !important;
    stroke: #0094ff !important;
}
.ts-pro-upgrade-feature,
.ts-pro-upgrade-heading,
.ts-pro-upgrade-desc,
.ts-pro-upgrade-body {
    text-align: center !important;
    margin-left: auto !important;
    margin-right: auto !important;
}
.ts-pro-upgrade-feature {
    margin: 0 0 10px !important;
    font-size: 12px !important;
    font-weight: 700 !important;
    letter-spacing: 0.06em !important;
    text-transform: uppercase !important;
    color: #0094ff !important;
}
.ts-pro-upgrade-heading {
    margin: 0 0 12px !important;
    font-size: 22px !important;
    font-weight: 700 !important;
    line-height: 1.35 !important;
    color: #222d32 !important;
    position: static !important;
}
.ts-pro-upgrade-desc {
    margin: 0 0 8px !important;
    max-width: 360px;
    font-size: 15px !important;
    line-height: 1.55 !important;
    color: #495057 !important;
}
.ts-pro-upgrade-body {
    margin: 0 0 26px !important;
    max-width: 360px;
    font-size: 14px !important;
    line-height: 1.55 !important;
    color: #6c757d !important;
}
.ts-pro-upgrade-card .primary-btn {
    margin: 0 auto;
}
</style>
<div class="ts-pro-upgrade-wrap">
    <div class="ts-pro-upgrade-card">
        <div class="ts-pro-upgrade-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="30" height="30">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
            </svg>
        </div>
        <?php if (!empty($featureName)) : ?>
            <p class="ts-pro-upgrade-feature"><?php echo $h($featureName); ?></p>
        <?php endif; ?>
        <h2 class="ts-pro-upgrade-heading"><?php echo $h($heading); ?></h2>
        <?php if (!empty($featureDescription)) : ?>
            <p class="ts-pro-upgrade-desc"><?php echo $h($featureDescription); ?></p>
        <?php endif; ?>
        <p class="ts-pro-upgrade-body"><?php echo $h($body); ?></p>
        <a class="primary-btn" href="<?php echo $h($upgradeUrl); ?>" target="_blank" rel="noopener noreferrer">
            <?php echo $h($buttonText); ?>
        </a>
    </div>
</div>
