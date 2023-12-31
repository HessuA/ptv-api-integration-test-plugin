<?php
/**
 * @Author: Timi Wahalahti
 * @Date:   2021-11-09 16:06:10
 * @Last Modified by:   Heikki Anttonen
 * @Last Modified time: 2023-12-11 15:47:23
 *
 * @package ptv-api-integration-test
 */

namespace PTV_Api_Integration_Test;

defined( 'ABSPATH' ) || exit;

function schedule_cron_events() {
  if ( ! \wp_next_scheduled( prefix_key( 'cron' ) ) ) {
    wp_schedule_event( time(), 'daily', prefix_key( 'cron' ) );
  }
} // end schedule_cron_events

function deschedule_cron_events() {
  wp_clear_scheduled_hook( prefix_key( 'cron' ) );
} // end deschedule_cron_events

function sync( $force = false ) {
  update_option( prefix_key( 'sync_end' ), null ); // set end to null for extra cleanup prevention if problems
  update_option( prefix_key( 'sync_start' ), wp_date( 'Y-m-d H:i:s' ) );

  $organization_id = '1ae7fc60-6dd6-4124-9445-be931b4b0953';

  // Get all services from current organization
  $response = call_api(
    'ServiceCollection/organization',
    [
      'organizationId' => $organization_id,
      'page'           => 1,
    ],
    [
      'timeout' => 30,
    ]
  );

  if ( ! $response || ! isset( $response['itemList'] ) ) {
    return;
  }

  // Save response item lists
  $item_lists = $response['itemList'];

  // Make sure you fetch all data, because api's page number is not allways one
  $current_page = $response['pageNumber'] + 1;
  while ( $current_page <= $response['pageCount'] ) {
    $response = call_api(
      'ServiceCollection/organization',
      [
        'organizationId' => $organization_id,
        'page'           => $current_page,
      ],
      [
        'timeout' => 30,
      ]
    );
    $item_lists = array_merge( $item_lists, $response['itemList'] );
    $current_page++;
  }

  // Save service items
  $services = [];

  foreach ( $item_lists as $item ) {
    foreach ( $item['services'] as $service ) {
      $id = $service['id'];

      // Fetch single service
      $response = call_api(
        "Service/{$id}",
        [
          'showHeader' => 'false',
        ],
        [
          'timeout' => 10,
        ]
      );

      if ( ! $response ) {
        continue;
      }

      $services[] = $response;

    }

  }

  // Save data
  foreach ( $services as $item ) {
    save_item( $item, $force );
  }

  update_option( prefix_key( 'sync_end' ), wp_date( 'Y-m-d H:i:s' ) );

  wp_schedule_single_event( time() + ( DAY_IN_SECONDS * 5 ), prefix_key( 'cleanup' ) );
} // end sync

function cleanup() {
  update_option( prefix_key( 'cleanup_start' ), wp_date( 'Y-m-d H:i:s' ) );

  cleanup_items();

  update_option( prefix_key( 'cleanup_end' ), wp_date( 'Y-m-d H:i:s' ) );
} // end cleanup
