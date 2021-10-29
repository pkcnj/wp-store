<?php

class WPM2AWS_WelcomePanel
{
    public function __construct()
    {
        add_action('admin_menu', array($this, 'loadAdminMenu'), 9);
    }

    public static function template()
    {
        $classes = 'welcome-panel';

        $vers = (array) get_user_meta(
            get_current_user_id(),
            'wpm2aws_hide_welcome_panel_on',
            true
        );

        // if (wpm2aws_version_grep(wpm2aws_version('only_major=1'), $vers)) {
        //     $classes .= ' hidden';
        // }
        ?>

        <div id="welcome-panel" class="<?php echo esc_attr($classes); ?>">
            <?php wp_nonce_field('wpm2aws-welcome-panel-nonce', 'welcomepanelnonce', false); ?>
            <a class="welcome-panel-close" href="<?php echo esc_url(menu_page_url('wpm2aws', false)); ?>"><?php echo esc_html(__('Dismiss', 'migration-2-aws')); ?></a>

            <div class="welcome-panel-content">
                <div class="welcome-panel-column-container">

                    <div style="max-width:25%;" class="welcome-panel-column  welcome-panel-first">

                        <p>
                        <img style="max-width:80%; margin-bottom: 14px;" src="<?php echo plugin_dir_url( dirname( __FILE__ ) ); ?>assets/images/aws-welcome-image.png"/>
                        </p>

                        <!--
                        <h3><span class="dashicons dashicons-shield" aria-hidden="true"></span> <?php echo esc_html(__("Before using this tool", 'migration-2-aws')); ?></h3>
                        <ul>
                        <li><?php //// echo esc_html(__("Understand the process.", 'migration-2-aws')); ?></li>
                        <li><?php /// echo esc_html(__("Read the documentation.", 'migration-2-aws')); ?></li>
                        <li><?php /// echo esc_html(__("Contact us if uncertain.", 'migration-2-aws')); ?></li>
                        </ul>
                        -->

                        </div>



                        <div style="max-width:25%;border-left:1px solid #d2d2d2;padding-left:25px; margin-top:8px;" class="welcome-panel-column  welcome-panel-middle">
                        <h3>Licence v.<?php echo constant("WPM2AWS_VERSION"); ?></h3>
                        <p>
                        <?php echo sprintf(
                            esc_html(
                                __(
                                    '%1$s to clone a site to AWS Now.',
                                    'migration-2-aws'
                                )
                            ),
                            wpm2awsHtmlLink(
                                __(
                                    'https://www.seahorse-data.com/checkout?edd_action=add_to_cart&download_id=8272',
                                    'migration-2-aws'
                                ),
                                __(
                                    'Get Credentials',
                                    'migration-2-aws'
                                ),
                                true,
                                array('target' => '_blank')
                            )
                        );
                        ?>
                        </p>

                        <p>
                        <?php
                        /* translators: links labeled 1: 'Migrate2AWS.com'*/
                        echo sprintf(
                            esc_html(
                                __(
                                    'View our %1$s.',
                                    'migration-2-aws'
                                )
                            ),
                            wpm2awsHtmlLink(
                                __(
                                    'https://www.seahorse-data.com/pricing/',
                                    'migration-2-aws'
                                ),
                                __(
                                    'Plan Options',
                                    'migration-2-aws'
                                ),
                                true,
                                array('target' => '_blank')
                            )
                        );
                        ?>
                        </p>

                    </div>

                        <div style="max-width:25%;border-left:1px solid #d2d2d2;padding-left:25px; margin-top:8px;" class="welcome-panel-column  welcome-panel-last">

                        <h3><?php echo esc_html(__("Tutorial", 'migration-2-aws')); ?></h3>
                       <p>
                        <?php echo sprintf(
                            esc_html(
                                __(
                                    '%1$s for AWS self-paced lab.',
                                    'migration-2-aws'
                                )
                            ),
                            wpm2awsHtmlLink(
                                __(
                                    'https://aws.amazon.com/getting-started/hands-on/migrating-a-wp-website/',
                                    'migration-2-aws'
                                ),
                                __(
                                    'Click Here',
                                    'migration-2-aws'
                                ),
                                true,
                                array('target' => '_blank')
                            )
                        );
                        ?>
                        </p>

                        <p>
                        <?php
                        /* translators: links labeled 1: 'Migrate2AWS.com'*/
                        echo sprintf(
                            esc_html(
                                __(
                                    '%1$s for Seahorse Support',
                                    'migration-2-aws'
                                )
                            ),
                            wpm2awsHtmlLink(
                                __(
                                    'https://www.seahorse-data.com/wp-on-aws-support-portal/',
                                    'migration-2-aws'
                                ),
                                __(
                                  'Click Here',
                                  'migration-2-aws'
                                ),
                                true,
                                array('target' => '_blank')
                            )
                        );
                        ?>
                        </p>


                        </div>

                </div>
            </div>
        </div>
        <?php
    }
}
