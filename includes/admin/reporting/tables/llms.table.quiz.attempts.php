<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Quizzes Reporting Table
 *
 * @since    [version]
 * @version  [version]
 */
class LLMS_Table_Quiz_Attempts extends LLMS_Admin_Table {

	/**
	 * Unique ID for the Table
	 * @var  string
	 */
	protected $id = 'quiz_attempts';

	/**
	 * Value of the field being filtered by
	 * Only applicable if $filterby is set
	 * @var  string
	 */
	protected $filter = 'any';

	/**
	 * Field results are filtered by
	 * @var  string
	 */
	protected $filterby = 'instructor';

	/**
	 * Is the Table Exportable?
	 * @var  boolean
	 */
	protected $is_exportable = false;

	/**
	 * Determine if the table is filterable
	 * @var  boolean
	 */
	protected $is_filterable = true;

	/**
	 * If true, tfoot will add ajax pagination links
	 * @var  boolean
	 */
	protected $is_paginated = true;

	/**
	 * Determine of the table is searchable
	 * @var  boolean
	 */
	protected $is_searchable = false;

	/**
	 * Results sort order
	 * 'ASC' or 'DESC'
	 * Only applicable of $orderby is not set
	 * @var  string
	 */
	protected $order = 'DESC';

	/**
	 * Field results are sorted by
	 * @var  string
	 */
	protected $orderby = 'id';

	/**
	 * Retrieve data for a cell
	 * @param    string     $key      the column id / key
	 * @param    obj        $attempt  LLMS_Quiz_Attempt obj
	 * @return   mixed
	 * @since    [version]
	 * @version  [version]
	 */
	protected function get_data( $key, $attempt ) {

		switch ( $key ) {

			case 'student':
				$value = $attempt->get_student()->get_name();
			break;

			case 'attempt':
				$value = '#' . $attempt->get( $key );
			break;

			case 'grade':
				$value = $attempt->get( $key ) ? $attempt->get( $key ) . '%' : '0%';
				$value .= ' (' . $attempt->l10n( 'status' ) . ')';
			break;

			case 'start_date':
			case 'end_date':
				$value = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $attempt->get( $key ) ) );
			break;

			case 'id':

				$value = sprintf( '%2$d (%1$s)', $attempt->get_key(), $attempt->get( 'id' ) );

				$url = LLMS_Admin_Reporting::get_current_tab_url( array(
					'tab' => 'quizzes',
					'stab' => 'attempts',
					'quiz_id' => $attempt->get( 'quiz_id' ),
					'attempt_id' => $attempt->get( 'id' ),
				) );

				$value = '<a href="' . esc_url( $url ) . '">' . $value . '</a>';

			break;

			default:
				$value = $key;

		}// End switch().

		return $value;
	}

	/**
	 * Retrieve a list of Instructors to be used for Filtering
	 * @return   array
	 * @since    [version]
	 * @version  [version]
	 */
	private function get_instructor_filters() {

		$query = get_users( array(
			'fields' => array( 'ID', 'display_name' ),
			'meta_key' => 'last_name',
			'orderby' => 'meta_value',
			'role__in' => array( 'administrator', 'lms_manager', 'instructor', 'instructors_assistant' ),
		) );

		$instructors = wp_list_pluck( $query, 'display_name', 'ID' );

		return $instructors;

	}

	/**
	 * Execute a query to retrieve results from the table
	 * @param    array      $args  array of query args
	 * @return   void
	 * @since    [version]
	 * @version  [version]
	 */
	public function get_results( $args = array() ) {

		$this->title = __( 'Quiz Attempts', 'lifterlms' );

		$args = $this->clean_args( $args );

		if ( isset( $args['page'] ) ) {
			$this->current_page = absint( $args['page'] );
		}

		$per = apply_filters( 'llms_reporting_' . $this->id . '_per_page', 25 );

		$this->order = isset( $args['order'] ) ? $args['order'] : $this->order;
		$this->orderby = isset( $args['orderby'] ) ? $args['orderby'] : $this->orderby;

		$this->filter = isset( $args['filter'] ) ? $args['filter'] : $this->get_filter();
		$this->filterby = isset( $args['filterby'] ) ? $args['filterby'] : $this->get_filterby();

		$query_args = array(
			'sort' => array( $this->orderby => $this->order ),
			'page' => $this->current_page,
			'per_page' => $per,
			'quiz_id' => $args['quiz_id'],
		);

		// if ( 'any' !== $this->filter ) {

		// 	$serialized_id = serialize( array(
		// 		'id' => absint( $this->filter ),
		// 	) );
		// 	$serialized_id = str_replace( array( 'a:1:{', '}' ), '', $serialized_id );

		// 	$query_args['meta_query'] = array(
		// 		array(
		// 			'compare' => 'LIKE',
		// 			'key' => '_llms_instructors',
		// 			'value' => $serialized_id,
		// 		),
		// 	);

		// }

		// if you can view others reports, make a regular query
		if ( current_user_can( 'view_others_lifterlms_reports' ) ) {

			$query = new LLMS_Query_Quiz_Attempt( $query_args );

		// user can only see their own reports, get a list of their students
		} elseif ( current_user_can( 'view_lifterlms_reports' ) ) {

			$instructor = llms_get_instructor();
			if ( ! $instructor ) {
				return;
			}
			$query = $instructor->get_courses( $query_args, 'query' );

		} else {

			return;

		}

		$this->max_pages = $query->max_pages;
		$this->is_last_page = $query->is_last_page();

		$this->tbody_data = $query->get_attempts();

	}

	/**
	 * Define the structure of arguments used to pass to the get_results method
	 * @return   array
	 * @since    [version]
	 * @version  [version]
	 */
	public function set_args() {
		return array(
			'quiz_id' => 0,
		);
	}

	/**
	 * Define the structure of the table
	 * @return   array
	 * @since    [version]
	 * @version  [version]
	 */
	protected function set_columns() {

		$cols = array(
			'id' => array(
				'exportable' => true,
				'title' => __( 'ID', 'lifterlms' ),
				'sortable' => true,
			),
			'attempt' => array(
				'exportable' => true,
				'title' => __( 'Attempt #', 'lifterlms' ),
				'sortable' => true,
			),
			'student' => array(
				'exportable' => true,
				'title' => __( 'Student', 'lifterlms' ),
				'sortable' => false,
			),
			'grade' => array(
				'exportable' => true,
				'title' => __( 'Grade', 'lifterlms' ),
				'sortable' => true,
			),
			'start_date' => array(
				'exportable' => true,
				'title' => __( 'Start Date', 'lifterlms' ),
				'sortable' => true,
			),
			'end_date' => array(
				'exportable' => true,
				'title' => __( 'End Date', 'lifterlms' ),
				'sortable' => true,
			),
		);

		return $cols;

	}

}