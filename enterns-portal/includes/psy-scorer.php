<?php
/**
 * Psychometric scoring engine — pure, testable class.
 *
 * Scoring rules (from Scoring_Config sheet):
 *  Sec1  Likert index (sum-min)/(max-min)*100; cluster averages.
 *  Sec2  Forced-choice A/B tally → one-liner preference profile.
 *  Sec3  Likert index; band: ≥75 self-directed | 50–74 capable | <50 structured.
 *  Sec4  Rank: report top-3 ranked drivers verbatim.
 *  Sec5  Likert index; reverse Y items before summing.
 *  Sec6  3 items/trait (C/E/ES/O/A); sum 3–15; normalise 0–100.
 *  Sec7  Compare answer to correct; score x/6. 5–6 strong | 3–4 adequate | ≤2 gap.
 *  Sec8  Open text — store verbatim, no score.
 *
 * Bands: 80–100 Strong | 60–79 Solid | 40–59 Mixed | 0–39 Watch.
 *
 * SECURITY: This class reads correct/reverse_scored from the DB.
 *           It must never expose those values in its output.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ENP_Psy_Scorer {

	/**
	 * @param int    $assessment_id
	 * @param string $selected_items_json  From psy_assessments.selected_items.
	 * @return array  All computed scores (never contains correct answers or reverse flags).
	 */
	public static function score( int $assessment_id, string $selected_items_json ): array {
		global $wpdb;
		$p = $wpdb->prefix;

		// ── 1. Load persisted paper (item_ids per section) ──────────────────────
		$persisted = json_decode( $selected_items_json, true );
		if ( ! is_array( $persisted ) ) {
			return self::empty_result();
		}

		// ── 2. Load items (with correct + reverse_scored) ────────────────────────
		$all_ids = array();
		foreach ( $persisted as $ids ) {
			$all_ids = array_merge( $all_ids, $ids );
		}
		if ( empty( $all_ids ) ) {
			return self::empty_result();
		}
		$placeholders = implode( ',', array_fill( 0, count( $all_ids ), '%s' ) );
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT item_id, section, type, reverse_scored, trait_or_cluster, question_text, correct
				 FROM {$p}psy_items WHERE item_id IN ($placeholders)",
				...$all_ids
			),
			ARRAY_A
		);
		$item_map = array();
		foreach ( $items as $item ) {
			$item_map[ $item['item_id'] ] = $item;
		}

		// ── 3. Load responses ────────────────────────────────────────────────────
		$responses = $wpdb->get_results( $wpdb->prepare(
			"SELECT item_id, answer_value FROM {$p}psy_responses WHERE assessment_id = %d",
			$assessment_id
		), ARRAY_A );
		$resp_map = array();
		foreach ( $responses as $r ) {
			$resp_map[ $r['item_id'] ] = $r['answer_value'];
		}

		// ── 4. Score each section ────────────────────────────────────────────────
		$result = array();

		// Sec1: Strengths — Likert index + cluster averages
		$sec1_ids    = $persisted[1] ?? array();
		$sec1_result = self::score_sec1( $sec1_ids, $item_map, $resp_map );
		$result['strengths_index']    = $sec1_result['index'];
		$result['strengths_clusters'] = $sec1_result['clusters'];

		// Sec2: Preferences — forced-choice A/B tally
		$sec2_ids = $persisted[2] ?? array();
		$result['preference_profile'] = self::score_sec2( $sec2_ids, $resp_map );

		// Sec3: Learning — Likert index
		$sec3_ids             = $persisted[3] ?? array();
		$result['learning_index'] = self::score_likert_index( $sec3_ids, $item_map, $resp_map );

		// Sec4: Motivation — top-3 ranked drivers
		$sec4_ids             = $persisted[4] ?? array();
		$result['motivation_top3'] = self::score_sec4( $sec4_ids, $item_map, $resp_map );

		// Sec5: Engagement — Likert index (with reverse)
		$sec5_ids             = $persisted[5] ?? array();
		$result['engagement_index'] = self::score_likert_index( $sec5_ids, $item_map, $resp_map );

		// Sec6: Personality — 5 Big Five traits
		$sec6_ids   = $persisted[6] ?? array();
		$sec6_result = self::score_sec6( $sec6_ids, $item_map, $resp_map );
		$result['trait_c']  = $sec6_result['C']  ?? null;
		$result['trait_e']  = $sec6_result['E']  ?? null;
		$result['trait_es'] = $sec6_result['ES'] ?? null;
		$result['trait_o']  = $sec6_result['O']  ?? null;
		$result['trait_a']  = $sec6_result['A']  ?? null;

		// Sec7: Reasoning — MCQ correct count x/6
		$sec7_ids            = $persisted[7] ?? array();
		$sec7_result         = self::score_sec7( $sec7_ids, $item_map, $resp_map );
		$result['reasoning_score'] = $sec7_result['score'];
		$result['reasoning_band']  = $sec7_result['band'];

		// Sec8: Open — store verbatim
		$sec8_ids = $persisted[8] ?? array();
		$result['open_responses'] = self::score_sec8( $sec8_ids, $item_map, $resp_map );

		// Overall band — average of numeric indices (excluding null/non-numeric).
		$result['overall_band'] = self::overall_band( $result );

		return $result;
	}

	// ── Section scorers ──────────────────────────────────────────────────────────

	private static function score_sec1( array $ids, array $item_map, array $resp_map ): array {
		$cluster_sums  = array();
		$cluster_count = array();
		$total = 0; $n = 0;

		foreach ( $ids as $id ) {
			$item  = $item_map[ $id ] ?? null;
			$raw   = isset( $resp_map[ $id ] ) ? (int) $resp_map[ $id ] : null;
			if ( ! $item || $raw === null ) continue;

			$val = self::apply_reverse( $raw, $item['reverse_scored'] );
			$total += $val; $n++;

			$cluster = strtoupper( (string) $item['trait_or_cluster'] );
			$cluster_sums[ $cluster ]  = ( $cluster_sums[ $cluster ] ?? 0 ) + $val;
			$cluster_count[ $cluster ] = ( $cluster_count[ $cluster ] ?? 0 ) + 1;
		}

		$index    = $n > 0 ? self::likert_index( $total, $n ) : null;
		$clusters = array();
		foreach ( $cluster_sums as $cl => $sum ) {
			$cnt = $cluster_count[ $cl ];
			$clusters[ $cl ] = self::likert_index( $sum, $cnt );
		}

		return array( 'index' => $index, 'clusters' => $clusters );
	}

	private static function score_sec2( array $ids, array $resp_map ): string {
		$tally_a = 0; $tally_b = 0;
		foreach ( $ids as $id ) {
			$ans = strtoupper( (string) ( $resp_map[ $id ] ?? '' ) );
			if ( 'A' === $ans ) $tally_a++;
			if ( 'B' === $ans ) $tally_b++;
		}
		$total = $tally_a + $tally_b;
		if ( 0 === $total ) return '';

		$pct_a = round( $tally_a / $total * 100 );
		if ( $pct_a >= 65 ) {
			return 'Analytical / Data-oriented';
		} elseif ( $pct_a <= 35 ) {
			return 'People-oriented / Collaborative';
		} else {
			return 'Balanced — analytical and people skills';
		}
	}

	private static function score_sec4( array $ids, array $item_map, array $resp_map ): array {
		$ranked = array();
		foreach ( $ids as $id ) {
			$raw = $resp_map[ $id ] ?? null;
			if ( $raw === null ) continue;
			$rank = (int) $raw;
			if ( $rank > 0 ) {
				$ranked[ $rank ] = $item_map[ $id ]['question_text'] ?? $id;
			}
		}
		ksort( $ranked );
		return array_values( array_slice( $ranked, 0, 3 ) );
	}

	private static function score_sec6( array $ids, array $item_map, array $resp_map ): array {
		$trait_sums  = array();
		$trait_count = array();

		foreach ( $ids as $id ) {
			$item  = $item_map[ $id ] ?? null;
			$raw   = isset( $resp_map[ $id ] ) ? (int) $resp_map[ $id ] : null;
			if ( ! $item || $raw === null ) continue;

			$val   = self::apply_reverse( $raw, $item['reverse_scored'] );
			$trait = strtoupper( (string) $item['trait_or_cluster'] );
			$trait_sums[ $trait ]  = ( $trait_sums[ $trait ] ?? 0 ) + $val;
			$trait_count[ $trait ] = ( $trait_count[ $trait ] ?? 0 ) + 1;
		}

		$result = array();
		foreach ( array( 'C', 'E', 'ES', 'O', 'A' ) as $trait ) {
			if ( ! isset( $trait_sums[ $trait ] ) ) {
				$result[ $trait ] = null;
				continue;
			}
			$sum = $trait_sums[ $trait ];
			$cnt = $trait_count[ $trait ];
			// Sec6: raw sum is 3–15 (3 items × 1–5 scale). Normalise 0–100.
			// min = 3, max = 15.
			$min = 3; $max = $cnt * 5;
			$result[ $trait ] = round( ( $sum - $min ) / ( $max - $min ) * 100, 2 );
		}
		return $result;
	}

	private static function score_sec7( array $ids, array $item_map, array $resp_map ): array {
		$correct = 0;
		$total   = count( $ids );
		foreach ( $ids as $id ) {
			$item    = $item_map[ $id ] ?? null;
			$given   = trim( (string) ( $resp_map[ $id ] ?? '' ) );
			$expected = trim( (string) ( $item['correct'] ?? '' ) );
			if ( $item && $expected !== '' && strcasecmp( $given, $expected ) === 0 ) {
				$correct++;
			}
		}
		if ( $correct >= 5 ) {
			$band = 'strong';
		} elseif ( $correct >= 3 ) {
			$band = 'adequate';
		} else {
			$band = 'gap';
		}
		return array( 'score' => $correct, 'band' => $band );
	}

	private static function score_sec8( array $ids, array $item_map, array $resp_map ): array {
		$out = array();
		foreach ( $ids as $id ) {
			$question = $item_map[ $id ]['question_text'] ?? $id;
			$answer   = (string) ( $resp_map[ $id ] ?? '' );
			$out[]    = array( 'question' => $question, 'answer' => $answer );
		}
		return $out;
	}

	// ── Generic Likert section scorer ────────────────────────────────────────────

	private static function score_likert_index( array $ids, array $item_map, array $resp_map ): ?float {
		$total = 0; $n = 0;
		foreach ( $ids as $id ) {
			$item  = $item_map[ $id ] ?? null;
			$raw   = isset( $resp_map[ $id ] ) ? (int) $resp_map[ $id ] : null;
			if ( ! $item || $raw === null ) continue;
			$val = self::apply_reverse( $raw, $item['reverse_scored'] );
			$total += $val; $n++;
		}
		return $n > 0 ? self::likert_index( $total, $n ) : null;
	}

	// ── Helpers ──────────────────────────────────────────────────────────────────

	/**
	 * Likert index: (sum - min) / (max - min) * 100.
	 * Assumes 1–5 scale; min = n, max = 5n.
	 */
	private static function likert_index( int $sum, int $n ): float {
		$min = $n;
		$max = $n * 5;
		if ( $max === $min ) return 0.0;
		return round( ( $sum - $min ) / ( $max - $min ) * 100, 2 );
	}

	/**
	 * Reverse-score a Likert item: 6 − raw.
	 */
	private static function apply_reverse( int $raw, string $flag ): int {
		return ( strtoupper( $flag ) === 'Y' ) ? ( 6 - $raw ) : $raw;
	}

	/**
	 * Determine overall band from numeric indices.
	 */
	private static function overall_band( array $result ): string {
		$indices = array(
			$result['strengths_index'],
			$result['learning_index'],
			$result['engagement_index'],
			$result['trait_c'],
			$result['trait_e'],
			$result['trait_es'],
			$result['trait_o'],
			$result['trait_a'],
		);
		$vals = array_filter( $indices, fn( $v ) => is_numeric( $v ) );
		if ( empty( $vals ) ) return 'unknown';
		$avg = array_sum( $vals ) / count( $vals );
		return self::band_label( $avg );
	}

	public static function band_label( float $score ): string {
		if ( $score >= 80 ) return 'Strong';
		if ( $score >= 60 ) return 'Solid';
		if ( $score >= 40 ) return 'Mixed';
		return 'Watch';
	}

	/**
	 * Learning orientation band (Sec3 specific).
	 */
	public static function learning_band( ?float $index ): string {
		if ( $index === null ) return 'unknown';
		if ( $index >= 75 ) return 'self-directed';
		if ( $index >= 50 ) return 'capable-with-support';
		return 'needs-structured-onboarding';
	}

	private static function empty_result(): array {
		return array(
			'strengths_index'    => null,
			'strengths_clusters' => null,
			'preference_profile' => '',
			'learning_index'     => null,
			'motivation_top3'    => null,
			'engagement_index'   => null,
			'trait_c'            => null,
			'trait_e'            => null,
			'trait_es'           => null,
			'trait_o'            => null,
			'trait_a'            => null,
			'reasoning_score'    => null,
			'reasoning_band'     => '',
			'open_responses'     => null,
			'overall_band'       => 'unknown',
		);
	}

	/**
	 * Persist computed scores to psy_scores table.
	 */
	public static function persist( int $assessment_id, array $scores ): void {
		global $wpdb;
		$p = $wpdb->prefix;

		$row = array(
			'assessment_id'    => $assessment_id,
			'strengths_index'  => $scores['strengths_index'],
			'strengths_clusters' => wp_json_encode( $scores['strengths_clusters'] ),
			'preference_profile' => (string) ( $scores['preference_profile'] ?? '' ),
			'learning_index'   => $scores['learning_index'],
			'motivation_top3'  => wp_json_encode( $scores['motivation_top3'] ),
			'engagement_index' => $scores['engagement_index'],
			'trait_c'          => $scores['trait_c'],
			'trait_e'          => $scores['trait_e'],
			'trait_es'         => $scores['trait_es'],
			'trait_o'          => $scores['trait_o'],
			'trait_a'          => $scores['trait_a'],
			'reasoning_score'  => $scores['reasoning_score'],
			'reasoning_band'   => (string) ( $scores['reasoning_band'] ?? '' ),
			'open_responses'   => wp_json_encode( $scores['open_responses'] ),
			'overall_band'     => (string) ( $scores['overall_band'] ?? '' ),
			'recommendation'   => '',
		);

		// Upsert (replace if already computed — idempotent on re-submit attempt).
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$p}psy_scores WHERE assessment_id = %d LIMIT 1",
			$assessment_id
		) );

		if ( $existing ) {
			$wpdb->update(
				"{$p}psy_scores",
				$row,
				array( 'assessment_id' => $assessment_id ),
				null,
				array( '%d' )
			);
		} else {
			$wpdb->insert( "{$p}psy_scores", $row );
		}
	}
}
