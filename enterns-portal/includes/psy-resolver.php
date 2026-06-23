<?php
/**
 * Psychometric resolver + rotation.
 * Builds a per-candidate paper by filtering psy_items, randomly selecting
 * the required count per section, and persisting item IDs + order.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ENP_Psy_Resolver {

	private string $region;
	private int    $edu_level;
	private string $field;

	public function __construct( string $region, int $edu_level, string $field ) {
		$this->region    = strtoupper( $region );
		$this->edu_level = $edu_level;
		$this->field     = strtoupper( $field );
	}

	/**
	 * Build the full paper (ordered list of item rows) for a candidate.
	 * Returns an array of sections, each containing an array of item rows
	 * (all columns including correct/reverse_scored for server-side use).
	 * Strips sensitive fields before returning the public paper.
	 *
	 * @param bool $strip_sensitive Strip correct/reverse_scored (for client).
	 * @return array<int, array<int, array<string,mixed>>>  Keyed by section number.
	 */
	public function resolve( bool $strip_sensitive = false ): array {
		global $wpdb;
		$p = $wpdb->prefix;

		// Load all items from DB, keyed by section.
		$rows = $wpdb->get_results(
			"SELECT * FROM {$p}psy_items ORDER BY item_id",
			ARRAY_A
		);

		$by_section = array();
		foreach ( $rows as $row ) {
			$by_section[ (int) $row['section'] ][] = $row;
		}

		$meta   = enp_psy_section_meta();
		$paper  = array();

		foreach ( $meta as $sec => $info ) {
			$pool     = $by_section[ $sec ] ?? array();
			$required = $info['count'];
			$selected = $this->select_section( $sec, $pool, $required );
			if ( $strip_sensitive ) {
				$selected = array_map( array( $this, 'strip_item' ), $selected );
			}
			$paper[ $sec ] = $selected;
		}

		return $paper;
	}

	/**
	 * Build the paper and persist selected_items JSON to the assessment row.
	 * Returns JSON string (stored in psy_assessments.selected_items).
	 */
	public function resolve_and_persist( int $assessment_id ): string {
		global $wpdb;
		$p = $wpdb->prefix;

		// Build full paper (with sensitive fields for scoring).
		$paper = $this->resolve( false );

		// Flatten to ordered list of item_ids per section.
		$persisted = array();
		foreach ( $paper as $sec => $items ) {
			$persisted[ $sec ] = array_map( function( $item ) {
				return $item['item_id'];
			}, $items );
		}

		$json = wp_json_encode( $persisted );

		$wpdb->update(
			"{$p}psy_assessments",
			array( 'selected_items' => $json ),
			array( 'id' => $assessment_id ),
			array( '%s' ),
			array( '%d' )
		);

		return $json;
	}

	/**
	 * Rebuild the ordered paper from a persisted selected_items JSON string.
	 * Returns section-keyed rows stripped of sensitive fields (for client use).
	 */
	public function rebuild_from_persisted( string $selected_items_json, bool $strip_sensitive = true ): array {
		global $wpdb;
		$p = $wpdb->prefix;

		$persisted = json_decode( $selected_items_json, true );
		if ( ! is_array( $persisted ) ) {
			return array();
		}

		$all_ids = array();
		foreach ( $persisted as $ids ) {
			$all_ids = array_merge( $all_ids, $ids );
		}
		if ( empty( $all_ids ) ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $all_ids ), '%s' ) );
		$rows_flat    = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$p}psy_items WHERE item_id IN ($placeholders)",
				...$all_ids
			),
			ARRAY_A
		);

		$by_id = array();
		foreach ( $rows_flat as $row ) {
			$by_id[ $row['item_id'] ] = $row;
		}

		$paper = array();
		foreach ( $persisted as $sec => $ids ) {
			$items = array();
			foreach ( $ids as $item_id ) {
				if ( isset( $by_id[ $item_id ] ) ) {
					$row = $by_id[ $item_id ];
					if ( $strip_sensitive ) {
						$row = $this->strip_item( $row );
					}
					// Shuffle MCQ/forced_choice options.
					$row = $this->shuffle_options( $row );
					$items[] = $row;
				}
			}
			$paper[ (int) $sec ] = $items;
		}

		return $paper;
	}

	// ── Private helpers ──────────────────────────────────────────────────────────

	private function select_section( int $sec, array $pool, int $required ): array {
		// Apply region + edu + field filters.
		$eligible = array_filter( $pool, array( $this, 'is_eligible' ) );
		$eligible = array_values( $eligible );

		// Section 6: must pick exactly 3 per trait (C, E, ES, O, A).
		if ( 6 === $sec ) {
			return $this->select_sec6( $eligible );
		}

		// Section 7: difficulty-weighted selection.
		if ( 7 === $sec ) {
			return $this->select_sec7( $eligible, $required );
		}

		if ( count( $eligible ) >= $required ) {
			$keys     = array_rand( $eligible, $required );
			$keys     = is_array( $keys ) ? $keys : array( $keys );
			$selected = array_map( fn( $k ) => $eligible[ $k ], $keys );
		} else {
			// Top up from ALL-field items not already included.
			$selected = $eligible;
			$all_field = array_filter( $pool, function( $item ) use ( $eligible ) {
				return 'ALL' === strtoupper( (string) $item['field'] )
					&& ! in_array( $item['item_id'], array_column( $eligible, 'item_id' ), true );
			} );
			$all_field = array_values( $all_field );
			$needed    = $required - count( $selected );
			if ( $needed > 0 && ! empty( $all_field ) ) {
				$pick  = min( $needed, count( $all_field ) );
				$keys  = array_rand( $all_field, $pick );
				$keys  = is_array( $keys ) ? $keys : array( $keys );
				$selected = array_merge( $selected, array_map( fn( $k ) => $all_field[ $k ], $keys ) );
			}
			if ( count( $selected ) < $required ) {
				enp_psy_log_content_gap(
					"Section {$sec}: needed {$required}, got " . count( $selected )
					. " (region={$this->region}, edu={$this->edu_level}, field={$this->field})"
				);
			}
		}

		// Shuffle order so items aren't always in the same sequence.
		shuffle( $selected );
		return $selected;
	}

	private function select_sec6( array $eligible ): array {
		$by_trait = array();
		foreach ( $eligible as $item ) {
			$trait = strtoupper( (string) $item['trait_or_cluster'] );
			$by_trait[ $trait ][] = $item;
		}

		$selected = array();
		foreach ( array( 'C', 'E', 'ES', 'O', 'A' ) as $trait ) {
			$pool_t = $by_trait[ $trait ] ?? array();
			if ( count( $pool_t ) >= 3 ) {
				$keys = array_rand( $pool_t, 3 );
				$keys = is_array( $keys ) ? $keys : array( $keys );
				foreach ( $keys as $k ) {
					$selected[] = $pool_t[ $k ];
				}
			} else {
				$selected = array_merge( $selected, $pool_t );
				enp_psy_log_content_gap(
					"Sec6 trait {$trait}: needed 3, got " . count( $pool_t )
					. " (region={$this->region}, edu={$this->edu_level}, field={$this->field})"
				);
			}
		}
		shuffle( $selected );
		return $selected;
	}

	private function select_sec7( array $eligible, int $required ): array {
		$edu = $this->edu_level;

		// Weight difficulty bands by education level.
		// Low edu (1–2): prefer difficulty 1–2; high edu (3–4): prefer 3–4.
		$preferred_diffs = ( $edu <= 2 ) ? array( 1, 2 ) : array( 3, 4 );
		$other_diffs     = ( $edu <= 2 ) ? array( 3, 4 ) : array( 1, 2 );

		// Prefer field-specific items.
		$field_items = array_values( array_filter( $eligible, function( $item ) {
			return strtoupper( (string) $item['field'] ) === $this->field;
		} ) );
		$generic_items = array_values( array_filter( $eligible, function( $item ) {
			return strtoupper( (string) $item['field'] ) !== $this->field;
		} ) );

		// Bucket by preferred/other difficulty.
		$pref_field   = array_values( array_filter( $field_items,   fn( $i ) => in_array( (int) $i['difficulty'], $preferred_diffs ) ) );
		$other_field  = array_values( array_filter( $field_items,   fn( $i ) => in_array( (int) $i['difficulty'], $other_diffs     ) ) );
		$pref_gen     = array_values( array_filter( $generic_items, fn( $i ) => in_array( (int) $i['difficulty'], $preferred_diffs ) ) );
		$other_gen    = array_values( array_filter( $generic_items, fn( $i ) => in_array( (int) $i['difficulty'], $other_diffs     ) ) );

		// Draw in priority order: pref_field → pref_gen → other_field → other_gen.
		$selected = array();
		$this->draw_into( $selected, $pref_field,  $required );
		$this->draw_into( $selected, $pref_gen,    $required );
		$this->draw_into( $selected, $other_field, $required );
		$this->draw_into( $selected, $other_gen,   $required );

		if ( count( $selected ) < $required ) {
			enp_psy_log_content_gap(
				"Sec7: needed {$required}, got " . count( $selected )
				. " (region={$this->region}, edu={$this->edu_level}, field={$this->field})"
			);
		}

		shuffle( $selected );
		return array_slice( $selected, 0, $required );
	}

	/**
	 * Draw up to ($max - count($dest)) items randomly from $source into $dest.
	 */
	private function draw_into( array &$dest, array $source, int $max ): void {
		$needed = $max - count( $dest );
		if ( $needed <= 0 || empty( $source ) ) {
			return;
		}
		$pick = min( $needed, count( $source ) );
		$keys = array_rand( $source, $pick );
		$keys = is_array( $keys ) ? $keys : array( $keys );
		foreach ( $keys as $k ) {
			$dest[] = $source[ $k ];
		}
	}

	private function is_eligible( array $item ): bool {
		// Region: item region must be 'ALL' or match candidate region.
		$item_region = strtoupper( (string) $item['region'] );
		if ( 'ALL' !== $item_region && $item_region !== $this->region ) {
			return false;
		}

		// Education level.
		$edu_min = $item['edu_min'];
		$edu_max = $item['edu_max'];
		if ( 'ALL' !== strtoupper( (string) $edu_min ) ) {
			if ( $this->edu_level < (int) $edu_min ) {
				return false;
			}
		}
		if ( 'ALL' !== strtoupper( (string) $edu_max ) ) {
			if ( $this->edu_level > (int) $edu_max ) {
				return false;
			}
		}

		// Field: item field must be 'ALL' or match candidate field.
		$item_field = strtoupper( (string) $item['field'] );
		if ( 'ALL' !== $item_field && $item_field !== $this->field ) {
			return false;
		}

		return true;
	}

	/**
	 * Remove server-side-only fields (correct, reverse_scored) from an item row.
	 */
	private function strip_item( array $item ): array {
		unset( $item['correct'], $item['reverse_scored'] );
		return $item;
	}

	/**
	 * Shuffle option order for MCQ and forced-choice items.
	 * Stores the shuffled order inside the item array.
	 */
	private function shuffle_options( array $item ): array {
		if ( ! in_array( $item['type'], array( 'mcq', 'forced_choice' ), true ) ) {
			return $item;
		}
		// Collect non-empty options with their keys.
		$opts = array();
		foreach ( array( 'option_a', 'option_b', 'option_c', 'option_d' ) as $key ) {
			if ( isset( $item[ $key ] ) && '' !== $item[ $key ] ) {
				$opts[ $key ] = $item[ $key ];
			}
		}
		if ( count( $opts ) <= 1 ) {
			return $item;
		}
		$values = array_values( $opts );
		shuffle( $values );
		$keys = array_keys( $opts );
		foreach ( $keys as $i => $key ) {
			$item[ $key ] = $values[ $i ];
		}
		return $item;
	}
}
