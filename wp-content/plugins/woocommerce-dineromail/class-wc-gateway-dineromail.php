<?php

/**
 * Plugin Name: WooCommerce DineroMail
 * Plugin URI: http://foro.dineromail.com/
 * Description: Módulode de Integración DineroMail para WooCommerce.
 * Author: DineroMail
 * Author URI: http://dineromail.com/
 * Version: 2.0
 * License: GPLv3 or later
 * Text Domain: woocommerce
 * Domain Path:
 */

/**
 * WooCommerce fallback notice.
 */
function wcdineromail_woocommerce_fallback_notice() {
    $message = '<div class="error">';
        $message .= '<p>' . __( 'WooCommerce DineroMail Gateway depends on the last version of <a href="http://wordpress.org/extend/plugins/woocommerce/">WooCommerce</a> to work!' , 'woocommerce' ) . '</p>';
    $message .= '</div>';

    echo $message;
}

/**
 * Load functions.
 */
add_action( 'plugins_loaded', 'wcdineromail_gateway_load', 0 );

function wcdineromail_gateway_load() {

    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
        add_action( 'admin_notices', 'wcdineromail_woocommerce_fallback_notice' );

        return;
    }

    
    /**
     * Add the gateway to WooCommerce.
     *
     * @access public
     * @param array $methods
     * @return array
     */
    add_filter( 'woocommerce_payment_gateways', 'wcdineromail_add_gateway' );

    function wcdineromail_add_gateway( $methods ) {
        $methods[] = 'WC_DineroMail_Gateway';
        return $methods;
    }

    /**
     * WC DineroMail Gateway Class.
     *
     * Built the DineroMail method.
     */
    class WC_DineroMail_Gateway extends WC_Payment_Gateway {

        /**
         * Constructor for the gateway.
         *
         * @return void
         */
        public function __construct() {
            global $woocommerce;

            $this->id             = 'dineromail';
            $this->icon           = plugins_url( 'images/icons/logoDM.jpg', __FILE__ );
            $this->has_fields     = false;
            $this->liveurl      = 'https://checkout.dineromail.com/CheckOut';
            $this->method_title   = __( 'DineroMail', 'woocommerce' );
 
            // Load the form fields.
            $this->init_form_fields();

            // Load the settings.
            $this->init_settings();

            // Define user set variables.           
            $this->title            = $this->settings['title'];
            $this->description      = $this->settings['description'];
            $this->cuenta    = $this->settings['cuenta'];
            $this->email            = $this->settings['email'];
            $this->country    = $this->settings['country'];
            $this->logo    = $this->settings['logo'];
            $this->ipnpass    = $this->settings['ipnpass'];     
            
            // Logs
            $this->log = $woocommerce->logger();            

            // Actions.
            add_action( 'woocommerce_receipt_dineromail', array( &$this, 'receipt_page' ) );
           
            if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
            } else {
                add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
            }

            // Payment Return
            add_action( 'woocommerce_api_wc_dineromail_gateway', array( $this, 'check_ipn_response' ) );

            // Valid for use.
            $this->enabled = ( 'yes' == $this->settings['enabled'] ) && ! empty( $this->email ) && $this->is_valid_for_use();

            // Checks if email is not empty.
            $this->email == '' ? add_action( 'admin_notices', array( &$this, 'mail_missing_message' ) ) : '';

            // Checks if token is not empty.
            $this->cuenta == '' ? add_action( 'admin_notices', array( &$this, 'cuenta_missing_message' ) ) : '';           
            
        }

        /**
         * Check if this gateway is enabled and available in the user's country.
         *
         * @return bool
         */
        public function is_valid_for_use() {
            if ( ! in_array( get_woocommerce_currency() , array( 'ARS', 'BRL', 'CLP', 'MXN', 'USD' ) ) ) {
                return false;
            }

            return true;
        }

        /**
         * Admin Panel Options.
         * - Options for bits like 'title' and availability on a country-by-country basis.
         *
         * @since 1.0.0
         */
        public function admin_options() {

            ?>
            <h3><?php _e( 'DineroMail Settings', 'woocommerce' ); ?></h3>
            <p><?php _e( 'Página de configuración del módulo de DineroMail para WooCommerce.', 'woocommerce' ); ?></p>
            <table class="form-table">
            <?php
                if ( ! $this->is_valid_for_use() ) {

                    // Valid currency.
                    echo '<div class="inline error"><p><strong>' . __( 'Gateway Disabled', 'woocommerce' ) . '</strong>: ' . __( 'DineroMail does not support your store currency.', 'woocommerce' ) . '</p></div>';

                } else {

                    // Generate the HTML For the settings form.
                    $this->generate_settings_html();
                }
            ?>
            </table>
            <?php
        }

        /**
         * Initialise Gateway Settings Form Fields.
         *
         * @return void
         */
        function init_form_fields() {

        $this->form_fields = array(
            'enabled' => array(
                            'title' => __( 'Activar/Desactivar', 'woocommerce' ),
                            'type' => 'checkbox',
                            'label' => __( 'Activar DineroMail', 'woocommerce' ),
                            'default' => 'yes'
                        ),
            'title' => array(
                            'title' => __( 'Título', 'woocommerce' ),
                            'type' => 'text',
                            'description' => __( '<br />Este és el título que el comprador verá durante el pago.', 'woocommerce' ),
                            'default' => __( 'DineroMail', 'woocommerce' )
                        ),
            'description' => array(
                            'title' => __( 'Descrición', 'woocommerce' ),
                            'type' => 'textarea',
                            'description' => __( 'Esta és la descripcion que e comprador usuário verá durante el pago.', 'woocommerce' ),
                            'default' => __("Pague con seguridad a través de DineroMail", 'woocommerce')
                        ),
            'cuenta' => array(
                            'title' => __( 'Número de Cuenta', 'woocommerce' ),
                            'type' => 'text',
                            'description' => __( '<br />Deve ser inserido sin la barra(/) y sin el último dígito.', 'woocommerce' ),
                            'default' => ''
                        ),
            'email' => array(
                            'title' => __( 'Mail de registro en DineroMail', 'woocommerce' ),
                            'type' => 'text',
                            'description' => __( '<br />Informe el mail que usou para se registrar en DineroMail.', 'woocommerce' ),
                            'default' => ''
                        ),
            'country' => array(
                            'title' => __( 'País de Registro', 'woocommerce' ),
                            'type' => 'select',
                            'description' => __( '<br />Informe el país de registro de su cuenta en DineroMail.', 'woocommerce' ),
                            'options' => array(
                                'Argentina' => __( 'Argentina', 'woocommerce' ),
                                'Brasil' => __( 'Brasil', 'woocommerce' ),
                                'Chile' => __( 'Chile', 'woocommerce' ),
                                'México' => __( 'México', 'woocommerce' )
                            )
                        ),
                        
            'logo' => array(
                            'title' => __( 'Logo de la Tienda', 'woocommerce' ),
                            'type' => 'text',
                            'description' => __( '<br />Informe la url donde está alojada la imagen que aparecerá en la parte superio de página de pago de DineroMail. Ejemplo: http://www.misitio/imagens/logo.jpg<br />Tamaño máximo qua se utiliza <b>756x100</b>.', 'woocommerce' ),
                            'default' => '',
                            'readyonli'
                        ),
            'ipnpass' => array(
                            'title' => __( 'Contraseña IPN', 'woocommerce' ),
                            'type' => 'text',
                            'description' => __( '<br />Informe la contraseña IPN caso v&aacute; usar la notificación de DineroMail.<br />Use la URL informada abajo para configurar la Notificación de DineroMail.<br />Para más informaciones referiente la IPN, consulte el manual IPN disponnible en la url <a href="https://ar.dineromail.com/biblioteca" target="_blank">https://ar.dineromail.com/biblioteca</a>.', 'woocommerce' ),
                            'default' => ''
                        ), 
            'ipnurl' => array(
                            'title' => __( 'URL IPN', 'woocommerce' ),
                            'type' => 'textarea',
                            'description' => __( 'Caso vá usar la notificación de DineroMail use la url ariba.<br />Para más Informaciones acerca de la URL IPN consulte el manual IPN disponible en la seguinte url <a href="https://ar.dineromail.com/biblioteca" target="_blank">https://ar.dineromail.com/biblioteca</a>', 'woocommerce' ),
                            'default' => str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'WC_DineroMail_Gateway', home_url( '/' ) ) )
                        )
            );

    }

        /**
         * Generate the args to form.
         *
         * @param  array $order Order data.
         * @return array
         */
        function get_dineromail_args( $order ) {
        global $woocommerce;

        $order_id = $order->id;
        
        if($this->country == "Argentina") {
            $currency = "ars";
            $language = "es";
            $countryid = 1;
        } 
        elseif($this->country == "Brasil") {
            $currency = "brl";
            $language = "pt";
            $countryid = 2;
        } 
        elseif($this->country == "Chile") {
            $currency = "clp";
            $language = "es";
            $countryid = 3;
        } 
        elseif($this->country == "México") {
            $currency = "mxn";
            $language = "es";
            $countryid = 4;
        }

        if ($this->debug=='yes') $this->log->add( 'dineromail', 'Generating payment form for order #' . $order_id . '. Notify URL: ' . trailingslashit(home_url()).'??wc-api=WC_DineroMail_Gateway');
        
        $dineromail_args = array_merge(
            array(
                'change_quantity' => '0',
                'merchant'        => $this->cuenta,
                'country_id'      => $countryid,
                'header_image'    => $this->logo,
                'seller_name'     => get_bloginfo( 'name' ),
                'language'        => $language,
                'transaction_id'  => 'wpw'.$order_id,
                'currency'        => $currency,
                'ok_url'          => add_query_arg( 'utm_nooverride', '1', $this->get_return_url( $order ) ),
                'pending_url'     => add_query_arg( 'utm_nooverride', '1', $this->get_return_url( $order ) ),
                'error_url'       => $order->get_cancel_order_url(),
                'buyer_name'      => $order->billing_first_name,
                'buyer_lastname'  => $order->billing_last_name,
                'buyer_email'     => $order->billing_email,
                'buyer_phone'     => str_replace( array( '(', '-', ' ', ')', '.' ), '', $order->billing_phone )
                
            )
        );

        if ( get_option('woocommerce_prices_include_tax')=='yes' || $order->get_order_discount() > 0 ) :
            $pricediscount = sprintf('%.1f',$order->get_order_discount());            
            // Discount
            $dineromail_args['display_additional_charge'] = 1;
            $dineromail_args['additional_fixed_charge'] = $pricediscount.'-';
            $dineromail_args['additional_fixed_charge_currency'] = get_woocommerce_currency();
        endif;    
                    
        $item_names = array();

        if (sizeof($order->get_items())>0) : foreach ($order->get_items() as $item) :
            if ($item['qty']) :

                $item_loop++;

                $product = $order->get_product_from_item($item);

                $item_name  = $item['name'];

                $item_meta = new WC_Order_Item_Meta( $item['item_meta'] );
                if ($meta = $item_meta->display( true, true )) :
                    $item_name .= ' ('.$meta.')';
                endif;

                $dineromail_args['item_name_'.$item_loop] = $item_name;
                $dineromail_args['item_quantity_'.$item_loop] = $item['qty'];
                $dineromail_args['item_ammount_'.$item_loop] = $order->get_item_total( $item, false );//$order->get_item_total( $item, true, true );
                $dineromail_args['item_currency_'.$item_loop] = get_woocommerce_currency();

            endif;
        endforeach; endif;
            // Shipping Cost
            
        if ( ( $order->get_shipping() + $order->get_shipping_tax() ) > 0 ) :
            $item_loop++;
            $dineromail_args['item_name_'.$item_loop] ='WPW - Taxa de Frete';
            $dineromail_args['item_quantity_'.$item_loop]   = '1';
            $dineromail_args['item_ammount_'.$item_loop]    = number_format( $order->get_shipping(), 2, '.', '' );//number_format( $order->get_shipping() + $order->get_shipping_tax() , 2, '.', '' );
            $dineromail_args['item_currency_'.$item_loop] = get_woocommerce_currency();
        endif;
        
        if ( $order->get_total_tax() > 0 ) :
            $item_loop++;
            $dineromail_args['item_name_'.$item_loop] ='WPW - Taxa de Imposto';
            $dineromail_args['item_quantity_'.$item_loop]  = '1';
            $dineromail_args['item_ammount_'.$item_loop]    = number_format( $order->get_total_tax() , 2, '.', '' );
            $dineromail_args['item_currency_'.$item_loop] = get_woocommerce_currency();
        endif;

        $dineromail_args = apply_filters( 'woocommerce_dineromail_args', $dineromail_args );

        return $dineromail_args;
    }

        /**
         * Generate the form.
         *
         * @param mixed $order_id
         * @return string
         */
        function generate_dineromail_form( $order_id ) {
        global $woocommerce;

        $order = new WC_Order( $order_id );
        
        $dineromail_adr = $this->liveurl;

        $dineromail_args = $this->get_dineromail_args( $order );

        $dineromail_args_array = array();

        foreach ($dineromail_args as $key => $value) {
            $dineromail_args_array[] = '<input type="hidden" name="'.esc_attr( $key ).'" value="'.esc_attr( $value ).'" />';
        }

        $woocommerce->add_inline_js('
            jQuery("body").block({
                    message: "<img src=\"' . esc_url( apply_filters( 'woocommerce_ajax_loader_url', $woocommerce->plugin_url() . '/assets/images/ajax-loader.gif' ) ) . '\" alt=\"Redirecting&hellip;\" style=\"float:left; margin-right: 10px;\" />'.__('Gracias por su compra. Usted será redirigido para página de pago de DineroMail.', 'woocommerce').'",
                    overlayCSS:
                    {
                        background: "#fff",
                        opacity: 0.6
                    },
                    css: {
                        padding:        28,
                        textAlign:      "center",
                        color:          "#555",
                        border:         "3px solid #aaa",
                        backgroundColor:"#fff",
                        cursor:         "wait",
                        lineHeight:     "32px"
                    }
                });
            jQuery("#submit_dineromail_payment_form").click();
        ');

        return '<form action="'.esc_url( $dineromail_adr ).'" method="post" id="dineromail_payment_form" target="_top">
                ' . implode('', $dineromail_args_array) . '
                <input type="submit" class="button-alt" id="submit_dineromail_payment_form" value="'.__('Pago a través de DineroMail', 'woocommerce').'" /> <a class="button cancel" href="'.esc_url( $order->get_cancel_order_url() ).'">'.__('Cancel order &amp; restore cart', 'woocommerce').'</a>
            </form>';

    }

        /**
         * Process the payment and return the result.
         *
         * @param int $order_id
         * @return array
         */
        public function process_payment( $order_id ) {

            $order = new WC_Order( $order_id );

            return array(
                'result'    => 'success',
                'redirect'  => add_query_arg( 'order', $order->id, add_query_arg( 'key', $order->order_key, get_permalink( woocommerce_get_page_id( 'pay' ) ) ) )
            );

        }

        /**
         * Output for the order received page.
         *
         * @return void
         */
        public function receipt_page( $order ) {
            global $woocommerce;

            echo '<p>' . __( 'Gracias por su compra, Haz clcick en el boton abajo para realizar el pago a través de DineroMail.', 'woocommerce' ) . '</p>';

            echo $this->generate_dineromail_form( $order );

            // Remove cart.
            $woocommerce->cart->empty_cart();
        }

    /**
     * Check for DineroMail IPN Response
     *
     * @access public
     * @return void
     */
    function check_ipn_response() {
        
            $_POST = stripslashes_deep($_POST);
            if($_REQUEST['Notificacion']){          
    
                ini_set("allow_url_fopen", 1); 
                ini_set("allow_url_include", 1);   
                
                $notificacion = htmlspecialchars_decode($_REQUEST['Notificacion']);
                $notificacion = str_replace("<?xml version='1.0'encoding='ISO-8859-1'?>", "", $notificacion);
                $notificacion = str_replace("<?xml version=\'1.0\'encoding=\'ISO-8859-1\'?>", "", $notificacion);
                $notificacion = str_replace("<?xmlversion=\'1.0\'encoding=\'ISO-8859-1\'?>", "", $notificacion);
                $notificacion = str_replace("<?xmlversion='1.0'encoding='ISO-8859-1'?>", "", $notificacion);
                $notificacion = str_replace("<?xml version='1.0' encoding='ISO-8859-1'?>", "", $notificacion);
                $notificacion = str_replace("<?xml version=\'1.0\' encoding=\'ISO-8859-1\'?>", "", $notificacion);
                
                $doc = new SimpleXMLElement($notificacion);
                $tipo_notificacion = $doc ->tiponotificacion;
                foreach ($doc ->operaciones ->operacion  as  $OPERACION){
                    $id_operacion = $OPERACION->id; 
                    $this->successful_request($id_operacion);  
                }                
            }
    }

        /**
     * Successful Payment!
     *
     * @access public
     * @param array $posted
     * @return void
     */
    function successful_request( $id_operacion ) {
        
        global $woocommerce;        
         
        $nrocta = $this->cuenta;
        $senhaipn = $this->ipnpass;  
        //var_dump($order);
        if($this->country == "Argentina") {
             $url="https://argentina.dineromail.com/Vender/Consulta_IPN.asp";
        } 
        elseif($this->country == "Brasil") {
            $url="https://brasil.dineromail.com/Vender/Consulta_IPN.asp";
        } 
        elseif($this->country == "Chile") {
            $url="https://chile.dineromail.com/Vender/Consulta_IPN.asp";
        } 
        elseif($this->country == "México") {
            $url="https://mexico.dineromail.com/Vender/Consulta_IPN.asp";
        }
        
        $data = 'DATA=<REPORTE><NROCTA>'.$nrocta.'</NROCTA><DETALLE><CONSULTA><CLAVE>'.$senhaipn.'</CLAVE><TIPO>1</TIPO><OPERACIONES><ID>'.$id_operacion.'</ID></OPERACIONES></CONSULTA></DETALLE></REPORTE>';
    
        $url = parse_url($url);    
        $host = $url['host'];    
        $path = $url['path'];           
        $fp = fsockopen($host, 80);        
        
        fputs($fp, "POST $path HTTP/1.1\r\n");    
        fputs($fp, "Host: $host\r\n");    
        //fputs($fp, "Referer: $referer\r\n");    
        fputs($fp, "Content-type: application/x-www-form-urlencoded\r\n");    
        fputs($fp, "Content-length: ". strlen($data) ."\r\n");    
        fputs($fp, "Connection: close\r\n\r\n");    
        fputs($fp, $data);    
        $result = ''; 
  
        while(!feof($fp)) {    
            // resultado del request    
            $result .= fgets($fp, 128);    
        }    
            
        // cierra conexion    
        fclose($fp);    
        // separa el header del content   
        $result = explode("\r\n\r\n", $result, 2);    
        //$header = isset($result[0]) ? $result[0] : '';    
        $content = isset($result[1]) ? $result[1] : '';  
   
    //Caso en IPN no va bien, descomentar esta linea
    /*
        $findme   = '<';
        $pos = strpos($content, $findme);
        $final = ">";
        $finalpos = strripos($content, $final);
        $finalpos=$finalpos-3;
        $content = substr($content, $pos, $finalpos);
        $content = str_replace('<?xml version="1.0" encoding="ISO-8859-1"?>', "", $content);
   
   */
        $xml = new SimpleXMLElement($content);  
        $estadoxml = $xml ->ESTADOREPORTE;         
        if($estadoxml==1){
            
            foreach ($xml ->DETALLE->OPERACIONES->OPERACION  as  $OPERACION){
    
                (int)$trx_id= str_replace("wpw", "", $OPERACION->ID);  
                $order = new WC_Order( $trx_id );  
               
                $estadotrans= $OPERACION->ESTADO; 
                if($estadotrans==1){
                    $order->update_status( 'pending', __( 'Payment pending by DineroMail.', 'woocommerce' ) );
                   $mailer = $woocommerce->mailer();

                        $message = $mailer->wrap_message(
                            __( 'Order Pending', 'woocommerce' ),
                            sprintf( __( 'Order %s has been marked as pending - DineroMail reason code: %s', 'woocommerce' ), $order->get_order_number(), '' )
                        );

                        $mailer->send( get_option( 'admin_email' ), sprintf( __( 'Payment for order %s Pending', 'woocommerce' ), $order->get_order_number() ), $message );                 
                }
                elseif($estadotrans==2){
                  $order->update_status( 'completed', __( 'Payment completed by DineroMail.', 'woocommerce' ) );
                    $mailer = $woocommerce->mailer();

                        $message = $mailer->wrap_message(
                            __( 'Order Completed', 'woocommerce' ),
                            sprintf( __( 'Order %s has been marked as completed - DineroMail reason code: %s', 'woocommerce' ), $order->get_order_number(), '' )
                        );

                        $mailer->send( get_option( 'admin_email' ), sprintf( __( 'Payment for order %s Completed', 'woocommerce' ), $order->get_order_number() ), $message );
                }
                elseif($estadotrans==3){
                    $order->update_status( 'cancelled', __( 'Payment cancelled by DineroMail.', 'woocommerce' ) );
                    $mailer = $woocommerce->mailer();

                        $message = $mailer->wrap_message(
                            __( 'Order Canceled', 'woocommerce' ),
                            sprintf( __( 'Order %s has been marked as canceled - DineroMaill reason code: %s', 'woocommerce' ), $order->get_order_number(), '' )
                        );

                        $mailer->send( get_option( 'admin_email' ), sprintf( __( 'Payment for order %s Canceled', 'woocommerce' ), $order->get_order_number() ), $message );
                }            
            }            
        }    
    }

        /**
         * Adds error message when not configured the email.
         *
         * @return string Error Mensage.
         */
        public function mail_missing_message() {
            $message = '<div class="error">';
                $message .= '<p>' . sprintf( __( '<strong>Gateway Desactivado</strong> Usted necesitas informar el mail de registro de su cuenta de DineroMai;. %sHaz click ahí para configurar!%s' , 'woocommerce' ), '<a href="' . get_admin_url() . 'admin.php?page=woocommerce_settings&amp;tab=payment_gateways">', '</a>' ) . '</p>';
            $message .= '</div>';

            echo $message;
        }

        public function cuenta_missing_message() {
            $message = '<div class="error">';
                $message .= '<p>' . sprintf( __( '<strong>Gateway Desactivado</strong> Usted necesitas informar su número de cuenta de DineroMail. %sHaz click ahí para configurar!%s' , 'woocommerce' ), '<a href="' . get_admin_url() . 'admin.php?page=woocommerce_settings&amp;tab=payment_gateways">', '</a>' ) . '</p>';
            $message .= '</div>';

            echo $message;
        }
    } 
} 