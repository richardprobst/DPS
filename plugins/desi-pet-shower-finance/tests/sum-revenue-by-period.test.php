<?php
require_once dirname( __DIR__ ) . '/includes/class-dps-finance-revenue-query.php';

class DPS_WPDB_Revenue_Query_Stub {
    public $posts = 'wp_posts';
    public $postmeta = 'wp_postmeta';
    public $prepared_params = [];
    public $last_sql = '';
    private $data;

    public function __construct( array $data ) {
        $this->data = $data;
    }

    public function prepare( $sql, $params ) {
        $this->prepared_params = $params;
        $this->last_sql        = $sql;
        return $params;
    }

    public function get_var( $prepared ) {
        // $prepared é o array retornado pelo stub prepare.
        $post_type = $prepared[0] ?? 'dps_agendamento';
        $start     = null;
        $end       = null;

        if ( strpos( $this->last_sql, 'date_meta.meta_value >= %s' ) !== false && isset( $prepared[1] ) ) {
            $start = $prepared[1];
        }
        if ( strpos( $this->last_sql, 'date_meta.meta_value <= %s' ) !== false ) {
            $end_index = $start ? 2 : 1;
            if ( isset( $prepared[ $end_index ] ) ) {
                $end = $prepared[ $end_index ];
            }
        }

        $total = 0;
        foreach ( $this->data as $row ) {
            if ( $post_type !== 'dps_agendamento' ) {
                continue;
            }
            if ( $start && $row['date'] < $start ) {
                continue;
            }
            if ( $end && $row['date'] > $end ) {
                continue;
            }
            $total += $row['total'];
        }

        return $total;
    }
}

$sample_data = [
    [ 'id' => 1, 'date' => '2024-05-01', 'total' => 1000 ],
    [ 'id' => 2, 'date' => '2024-05-15', 'total' => 2500 ],
    [ 'id' => 3, 'date' => '2024-06-02', 'total' => 3000 ],
];

$wpdb = new DPS_WPDB_Revenue_Query_Stub( $sample_data );

$total_may = DPS_Finance_Revenue_Query::sum_by_period( '2024-05-01', '2024-05-31', $wpdb );
if ( $total_may !== 3500 ) {
    throw new RuntimeException( 'Falha ao somar maio: esperado 3500, obtido ' . $total_may );
}

$total_june = DPS_Finance_Revenue_Query::sum_by_period( '2024-06-01', '2024-06-30', $wpdb );
if ( $total_june !== 3000 ) {
    throw new RuntimeException( 'Falha ao somar junho: esperado 3000, obtido ' . $total_june );
}

$total_full = DPS_Finance_Revenue_Query::sum_by_period( '2024-05-01', '2024-06-30', $wpdb );
if ( $total_full !== 6500 ) {
    throw new RuntimeException( 'Falha ao somar bimestre: esperado 6500, obtido ' . $total_full );
}

echo "Tests passed" . PHP_EOL;
