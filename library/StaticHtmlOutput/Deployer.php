<?php

class Deployer {

    public function __construct() {
        $target_settings = array(
            'general',
            'advanced',
        );

        if ( defined( 'WP_CLI' ) ) {
            require_once dirname( __FILE__ ) .
                '/../StaticHtmlOutput/DBSettings.php';

            $this->settings =
                WPSHO_DBSettings::get( $target_settings );
        } else {
            require_once dirname( __FILE__ ) .
                '/../StaticHtmlOutput/PostSettings.php';

            $this->settings =
                WPSHO_PostSettings::get( $target_settings );
        }
    }

    public function deploy( $test = false ) {
        $method = $this->settings['selected_deployment_option'];

        WP_CLI::log( 'Deploying static site via: ' . $method );

        $start_time = microtime( true );

        $powerpack_dir = dirname( __FILE__ ) . '/../../powerpack';

        switch ( $this->settings['selected_deployment_option'] ) {
            case 'folder':
                break;
            case 'zip':
                break;
            case 's3':
                require_once dirname( __FILE__ ) .
                    '/../StaticHtmlOutput/SitePublisher.php';

                require_once $powerpack_dir . '/S3.php';

                if ( $test ) {
                    error_log( 'testing s3 deploy' );
                    $s3->test_s3();
                    return;
                }

                $s3->bootstrap();
                $s3->loadArchive();
                $s3->prepareDeploy();
                $s3->upload_files();
                $s3->cloudfront_invalidate_all_items();
                break;
            case 'bitbucket':
                require_once dirname( __FILE__ ) .
                    '/../StaticHtmlOutput/SitePublisher.php';

                require_once $powerpack_dir . '/Bitbucket.php';

                if ( $test ) {
                    error_log( 'testing bitbucket deploy' );
                    $bitbucket->test_upload();
                    return;
                }

                $bitbucket->bootstrap();
                $bitbucket->loadArchive();
                $bitbucket->prepareDeploy( true );
                $bitbucket->upload_files();
                break;
            case 'bunnycdn':
                require_once dirname( __FILE__ ) .
                    '/../StaticHtmlOutput/SitePublisher.php';

                require_once $powerpack_dir . '/BunnyCDN.php';

                if ( $test ) {
                    error_log( 'testing BunnyCDN deploy' );
                    $bunny->test_deploy();
                    return;
                }

                $bunny->bootstrap();
                $bunny->loadArchive();
                $bunny->prepareDeploy( true );
                $bunny->upload_files();
                $bunny->purge_all_cache();
                break;
            case 'ftp':
                require_once dirname( __FILE__ ) .
                    '/../StaticHtmlOutput/SitePublisher.php';

                require_once $powerpack_dir . '/FTP.php';

                if ( $test ) {
                    error_log( 'testing FTP deploy' );
                    $ftp->test_ftp();
                    return;
                }

                $ftp->bootstrap();
                $ftp->loadArchive();
                $ftp->prepareDeploy();
                $ftp->upload_files();
                break;
            case 'github':
                require_once dirname( __FILE__ ) .
                    '/../StaticHtmlOutput/SitePublisher.php';

                require_once $powerpack_dir . '/GitHub.php';

                if ( $test ) {
                    error_log( 'testing GitHub deploy' );
                    $github->test_upload();
                    return;
                }

                $github->bootstrap();
                $github->loadArchive();
                $github->prepareDeploy( true );
                $github->upload_files();
                break;
            case 'gitlab':
                require_once dirname( __FILE__ ) .
                    '/../StaticHtmlOutput/SitePublisher.php';

                require_once $powerpack_dir . '/GitLab.php';

                if ( $test ) {
                    error_log( 'testing GitLab deploy' );
                    $gitlab->test_file_create();
                    return;
                }

                $gitlab->bootstrap();
                $gitlab->loadArchive();
                $gitlab->getListOfFilesInRepo();

                $gitlab->prepareDeploy( true );
                $gitlab->upload_files();
                break;
            case 'netlify':
                require_once $powerpack_dir . '/Netlify.php';

                if ( $test ) {
                    error_log( 'testing Netlify deploy' );
                    $netlify->test_netlify();
                    return;
                }

                $netlify->deploy();
                break;
        }

        $end_time = microtime( true );

        $duration = $end_time - $start_time;

        WP_CLI::success(
            'Deployed to: ' . $method . ' in ' .
            date( 'H:i:s', $duration )
        );

        $this->finalizeDeployment();
    }

    public function finalizeDeployment() {
        $this->emailDeployNotification();
        $this->triggerPostDeployHooks();
    }

    public function emailDeployNotification() {
        if ( ! isset( $this->settings['completionEmail'] ) ) {
            return;
        }

        WP_CLI::line( 'Sending confirmation email...' );
        $current_user = wp_get_current_user();
        $to = $current_user->user_email;
        $subject = 'Static site deployment: ' .
            $site_title = get_bloginfo( 'name' );
        $body = 'Your WordPress site has been automatically deployed.';
        $headers = array( 'Content-Type: text/html; charset=UTF-8' );

        wp_mail( $to, $subject, $body, $headers );
    }

    public function triggerPostDeployHooks() {
        require_once dirname( __FILE__ ) .
            '/Archive.php';
        $this->archive = new Archive();
        $this->archive->setToCurrentArchive();

        do_action( 'wp2static_post_deploy_trigger', $this->archive );
    }
}
