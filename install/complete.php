<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <title>Installation Complete - Task Session</title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.png"/>
    <link href="../assets/css/bootstrap.css" rel="stylesheet" type="text/css"/>
    <link href="../assets/css/style.min.css" rel="stylesheet" type="text/css"/>
    <link href="https://fonts.googleapis.com/css2?family=Lexend+Deca:wght@400;600&display=swap" rel="stylesheet"/>
    <link href="style.css" rel="stylesheet" type="text/css"/>
</head>
<body class="install-complete-page">
    <div class="install-logo">
        <img src="../assets/images/svg/dark-logo.svg" alt="Task Session"/>
    </div>
    <div class="dbinstall center-col install-complete-wrap">
        <div class="database">
            <div class="login-cols">
                <div class="install-complete-header">
                    <div class="install-complete-header__icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="40" height="40">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                        </svg>
                    </div>
                    <h1>Congratulations!</h1>
                    <p class="install-complete-header__lead">Your Task Session system was installed successfully.</p>
                </div>

                <div class="install-complete-card install-complete-card--info">
                    <div class="install-complete-card__title">
                        <span class="install-complete-card__icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20" height="20">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 0 1-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 0 0 6.16-12.12A14.98 14.98 0 0 0 9.631 8.41m5.96 5.96a14.926 14.926 0 0 1-5.841 2.58m-.119-8.54a6 6 0 0 0-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 0 0-2.58 5.841m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 0 1-2.448-2.448 14.9 14.9 0 0 1 .06-.312m-2.24 2.39a4.493 4.493 0 0 0-1.757 4.306 4.493 4.493 0 0 0 4.306-1.758M16.5 9a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z"/>
                            </svg>
                        </span>
                        Installation Complete
                    </div>
                    <p>Your Task Session installation is ready. You can start managing projects, tasks, and team collaboration right away.</p>
                </div>

                <div class="install-complete-card install-complete-card--warning">
                    <div class="install-complete-card__title">
                        <span class="install-complete-card__icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20" height="20">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
                            </svg>
                        </span>
                        Security Reminder
                    </div>
                    <p><strong>Important:</strong> Remove the <code>install</code> folder from your server to prevent unauthorized access.</p>
                </div>

                <div class="install-complete-card install-complete-card--steps">
                    <div class="install-complete-card__title">
                        <span class="install-complete-card__icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20" height="20">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm0 5.25h.007v.008H3.75v-.008Zm0 5.25h.007v.008H3.75v-.008Z"/>
                            </svg>
                        </span>
                        Next Steps
                    </div>
                    <ul>
                        <li>Log in to your dashboard using your admin credentials</li>
                        <li>Configure your system settings and preferences</li>
                        <li>Add team members and create your first project</li>
                        <li>Explore features and start managing tasks</li>
                    </ul>
                </div>

                <?php
                $base_url = dirname(dirname($_SERVER['SCRIPT_NAME']));
                if (substr($base_url, -1) !== '/') {
                    $base_url .= '/';
                }
                ?>
                <a href="<?php echo htmlspecialchars($base_url . 'index.php', ENT_QUOTES, 'UTF-8'); ?>" class="primary-btn install-complete-btn">
                    Login to your dashboard
                </a>
            </div>
            <div class="clearfix"></div>
        </div>
    </div>
</body>
</html>
