<?php
/*
Plugin Name: Structured data for Events Manager
Plugin URI: http://agmialdea.info/events-manager-structured-data
Description: Automatically adds structured data to events posts created with the Events Manager plugin by JSON-LD method
Author: Alejandro Gil Mialdea
Author URI: https://agmialdea.info

Text Domain: em-schemaorg
Domain Path: /languages/

Version: 0.1
Depends: events-manager
License: GPLv3
*/

// Evitar el acceso directo al plugin
if ( !defined( 'ABSPATH' ) ) {
	die( '¡Buen intento! ;)' );
}

// Localización
add_action( 'plugins_loaded', function ()
{
	load_plugin_textdomain( 'em-schemaorg', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
} );

// No activar si no está activo Events Manager
add_action( 'admin_init', function ()
{    
    if ( !is_plugin_active( 'events-manager/events-manager.php' ) )
    {
        deactivate_plugins( plugin_basename(__FILE__) );
        add_action( 'admin_notices', function ()
        {
            $class = 'notice notice-warning is-dismissible';
            $message = sprintf( wp_kses( __( 'El plugin <strong>SCHEMA.ORG para Events Manager</strong> se ha desactivado. Su activación depende de que el plugin <a href="%1$s" class="%2$s">Events Manager</a> esté activo.', 'em-schemaorg' ), array(  'a' => array( 'href' => array(), 'class' => array() ), 'strong' => array() ) ), esc_url( get_admin_url(null, 'plugin-install.php?tab=plugin-information&amp;plugin=events-manager&amp;TB_iframe=true&amp;width=600&amp;height=550') ), esc_attr( 'thickbox open-plugin-details-modal' ) );

            printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message ); 
        } );
    }

}, 20 );

// 
add_action( 'wp_footer', function () {
    
    // Si se trata de un EVENTO
    if ( is_singular('event') ) :
    global $wpdb;
    
    // Abrimos cadena del script
    $ems_jsonld = '<script type="application/ld+json">
    [{
      "@context": "http://schema.org",
      "@type": "Event",
      ';
    
    // Título del evento
    $ems_titulo_yoast = get_post_meta( get_the_ID(), '_yoast_wpseo_title', true );
    if ( empty( $ems_titulo_yoast ) )
    {
        $ems_jsonld .= '"name": "' . get_the_title() . '",
        ';
    }
    else
    {
        $ems_jsonld .= '"name": "' . $ems_titulo_yoast . '",
        ';
    }
    
    // Descripción del evento
    $ems_descrip_yoast = get_post_meta( get_the_ID(), '_yoast_wpseo_metadesc', true );
    if ( empty( $ems_descrip_yoast ) )
        $ems_jsonld .= '"description": "' . get_the_excerpt() . '",
        ';
    else
        $ems_jsonld .= '"description": "' . $ems_descrip_yoast . '",
        ';
    
    // URL del evento
    $ems_jsonld .= '"url": "' . get_permalink() . '",
    ';
    
    // Imagen del evento
    if ( get_the_post_thumbnail_url( get_the_ID(), 'full' ) )
    {
        $ems_jsonld .= '"image": "' . get_the_post_thumbnail_url( get_the_ID(), 'full' ) . '",
        ';
    }
    
    
    // Fechas
    
    /* Obtenemos los datos de la hora:
    si se ha marcado el check "Todo el día" las horas serán de 00:00 a 23:59
    en caso contrario las horas serán las marcadas en los campos de hora inicio y hora fin */
    
    $ems_todoeldia = get_post_meta( get_the_ID(), '_event_all_day', true );
    
    // Fecha de inicio
    $ems_jsonld .= '"startDate": "' . get_post_meta( get_the_ID(), '_event_start_date', true ) . 'T' . ( ($ems_todoeldia) ? '00:00' : get_post_meta( get_the_ID(), '_event_start_time', true ) ) . '",
    ';
    
    // Apertura de puertas
    // $ems_jsonld .= '"doorTime": "18:30",';
    
    // Fecha de fin
    $ems_jsonld .= '"endDate": "' . get_post_meta( get_the_ID(), '_event_end_date', true ) . 'T' . ( ($ems_todoeldia) ? '23:59' : get_post_meta( get_the_ID(), '_event_end_time', true ) ) . '"
    ';
    
    
    // Si se han habilitado las ubicaciones obtendremos sus datos
    if ( get_option('dbem_locations_enabled') )
    {
        $ems_localizacion = $wpdb->get_row( "SELECT location_name, location_address, location_town, location_state, location_postcode, location_region, location_country, location_latitude, location_longitude FROM " . $wpdb->prefix . "em_locations WHERE location_id = " . get_post_meta( get_the_ID(), '_location_id', true ), ARRAY_A );
        
        $ems_jsonld .= ',
        "location": [{
            "@type": "Place",
            "name": "' . $ems_localizacion['location_name'] . '",
            "address": {
              "@type": "PostalAddress",
              "streetAddress": "' . $ems_localizacion['location_address'] . '",
              "addressLocality": "' . $ems_localizacion['location_town'] . '",
              "postalCode": "' . $ems_localizacion['location_postcode'] . '",
              "addressRegion": "' . $ems_localizacion['location_state'] . '",
              "addressCountry": "' . $ems_localizacion['location_country'] . '"
            }';
            
            $ems_latitud = $ems_localizacion['location_latitude'];
            $ems_longitud = $ems_localizacion['location_longitude'];
            if ( !empty($ems_latitud) && !empty($ems_longitud) )
            {
                $ems_jsonld .= ',
                    "geo": {
                      "@type": "GeoCoordinates",
                      "latitude": "' . $ems_localizacion['location_latitude'] . '",
                      "longitude": "' . $ems_localizacion['location_longitude'] . '"
                    }';   
            }
          
        $ems_jsonld .= '}]
        ';
    }
    
    // Si se han habilitado las reservas obtendremos sus datos
    if ( get_option('dbem_rsvp_enabled') && get_post_meta( get_the_ID(), '_event_rsvp', true ) )
    {
        $ems_tickets = $wpdb->get_row( "SELECT MAX(ticket_price) AS highPrice, MIN(ticket_price) AS lowPrice FROM " . $wpdb->prefix . "em_tickets WHERE event_id = " . get_post_meta( get_the_ID(), '_event_id', true ), ARRAY_A );
        
        if ( $ems_tickets['highPrice'] == $ems_tickets['ticket_price'])
        {
            $ems_jsonld .= ',
                "offers": [{
                    "@type": "Offer",
                    "price": "' . $ems_tickets['highPrice'] . '",
                    "priceCurrency": "' . get_option('dbem_bookings_currency') . '"
                  }]
                ';
        } else
        {
            $ems_jsonld .= ',
                "offers": [{
                    "@type": "AggregateOffer",
                    "lowPrice": "' . $ems_tickets['lowPrice'] . '",
                    "highPrice": "' . $ems_tickets['highPrice'] . '",
                    "priceCurrency": "' . get_option('dbem_bookings_currency') . '"
                  }]
                ';
        }
    }
    
    // Cerramos cadena del script
    $ems_jsonld .= '}]
    </script>';
    
    echo $ems_jsonld;
    
    endif;
    
} );


/* EOF */
