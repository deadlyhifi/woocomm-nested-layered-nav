<?php

class WidgetNestedLayeredNav extends WC_Widget_Layered_Nav
{
    /**
     * Show list based layered nav.
     * @param  array $terms
     * @param  string $taxonomy
     * @param  string $query_type
     * @return bool Will nav display?
     */
    protected function layered_nav_list( $terms, $taxonomy, $query_type ) {
        $term_counts = $this->get_filtered_term_product_counts( wp_list_pluck( $terms, 'term_id' ), $taxonomy, $query_type );
        $_chosen_attributes = WC_Query::get_layered_nav_chosen_attributes();
        $found = false;

        $hierarchy = [];

        foreach ( $terms as $term ) {
            $current_values    = isset( $_chosen_attributes[ $taxonomy ]['terms'] ) ? $_chosen_attributes[ $taxonomy ]['terms'] : array();
            $option_is_set     = in_array( $term->slug, $current_values );
            $count             = isset( $term_counts[ $term->term_id ] ) ? $term_counts[ $term->term_id ] : 0;

            // skip the term for the current archive
            if ($count === 0 || $this->get_current_term_id() === $term->term_id) {
                continue;
            }

            // Only show options with count > 0
            if ( 0 < $count ) {
                $found = true;
            } elseif ( 'and' === $query_type && 0 < $count && ! $option_is_set ) {
                continue;
            }

            $filter_name    = 'filter_' . sanitize_title( str_replace( 'pa_', '', $taxonomy ) );
            $current_filter = isset( $_GET[ $filter_name ] ) ? explode( ',', wc_clean( $_GET[ $filter_name ] ) ) : array();
            $current_filter = array_map( 'sanitize_title', $current_filter );

            if ( ! in_array( $term->slug, $current_filter ) ) {
                $current_filter[] = $term->slug;
            }

            $link = $this->get_page_base_url( $taxonomy );

            // Add current filters to URL.
            foreach ( $current_filter as $key => $value ) {
                // Exclude query arg for current term archive term
                if ( $value === $this->get_current_term_slug() ) {
                    unset( $current_filter[ $key ] );
                }

                // Exclude self so filter can be unset on click.
                if ( $option_is_set && $value === $term->slug ) {
                    unset( $current_filter[ $key ] );
                }
            }

            if ( ! empty( $current_filter ) ) {
                $link = add_query_arg( $filter_name, implode( ',', $current_filter ), $link );

                // Add Query type Arg to URL
                if ( $query_type === 'or' && ! ( 1 === sizeof( $current_filter ) && $option_is_set ) ) {
                    $link = add_query_arg( 'query_type_' . sanitize_title( str_replace( 'pa_', '', $taxonomy ) ), 'or', $link );
                }
            }

            if ((int) $term->parent === 0) {
                $hierarchy[$term->term_id]['parent'] = $this->build_list_item($term, 'parent', $option_is_set, $count, $link);
            } else {
                $hierarchy[$term->parent]['children'][] = $this->build_list_item($term, 'child', $option_is_set, $count, $link);
            }
        }

        // List display
        echo '<ul>';
        foreach ($hierarchy as $filter) {
            echo $filter['parent'];
            if (isset($filter['children']) && ! empty(array_filter($filter['children']))) {
                echo '<ul>';
                foreach ($filter['children'] as $child) {
                    echo $child . '</li>';
                }
                echo '</ul>';
            }
            echo '</li>'; // close list
        }
        echo '</ul>';

        return $found;
    }

    protected function build_list_item($term, $relationhip, $option_is_set, $count, $link)
    {
        $listItem = '<li class="wc-layered-nav-term' . ' ' . $relationhip . ( $option_is_set ? ' chosen' : '' ) . '">';
        $listItem .= ( $count > 0 || $option_is_set ) ? '<a href="' . esc_url( apply_filters( 'woocommerce_layered_nav_link', $link ) ) . '">' : '<span>';
        $listItem .= esc_html( $term->name );
        $listItem .= ( $count > 0 || $option_is_set ) ? '</a> ' : '</span> ';
        $listItem .= apply_filters( 'woocommerce_layered_nav_count', '<span class="count">(' . absint( $count ) . ')</span>', $count, $term );

        return $listItem;
    }
}
