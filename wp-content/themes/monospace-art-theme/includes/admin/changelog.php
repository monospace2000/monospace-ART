<?php
/**
 * Appearance > Changelog (2026-11-17, owner: "make it a page under Appearance, like the About page, and update it as we go along and finish
 * tasks"): everything that was added or fixed on the site, newest first, from changelog-data.php. A plain list, not a panel per entry (Admin UI
 * style): one line each - the day, the area, what changed - with a filter by area and a search box. Read-only.
 *
 * To add to it: put new entries at the top of changelog-data.php when a task is finished (the file says how).
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', function () {
    add_theme_page( 'Changelog', 'Changelog', 'edit_theme_options', 'monospace-art-changelog', 'monospace_art_changelog_page' );
} );

/** [ [ 'YYYY-MM-DD', 'Area', 'Text' ], ... ], newest first (a day's entries keep the order they are written in) */
function monospace_art_changelog_entries() {
    $entries = include __DIR__ . '/changelog-data.php';
    $entries = is_array( $entries ) ? array_values( $entries ) : [];
    foreach ( $entries as $i => &$e ) $e['_i'] = $i;
    unset( $e );
    usort( $entries, function ( $a, $b ) { return [ $b[0], $a['_i'] ] <=> [ $a[0], $b['_i'] ]; } );
    return $entries;
}

function monospace_art_changelog_page() {
    $entries = monospace_art_changelog_entries();
    $areas   = [];
    $months  = [];
    foreach ( $entries as $e ) {
        $areas[ $e[1] ] = ( $areas[ $e[1] ] ?? 0 ) + 1;
        $months[ substr( $e[0], 0, 7 ) ][] = $e;
    }
    ksort( $areas );
    $latest = $entries ? $entries[0][0] : '';
    $url    = fn( $path ) => esc_url( admin_url( $path ) );
    ?>
    <div class="wrap ms-changelog">
        <h1>Changelog <span class="ms-cl-chip"><?php echo count( $entries ); ?> changes</span><?php if ( $latest ) : ?> <span class="ms-cl-chip is-on">latest <?php echo esc_html( date_i18n( 'M j, Y', strtotime( $latest ) ) ); ?></span><?php endif; ?></h1>
        <p class="ms-cl-lead">What was added to and fixed on <a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></a>, newest first. It is kept up to date as each task is finished.
            How the parts work is on the <a href="<?php echo $url( 'themes.php?page=monospace-art-about' ); ?>">About monospace ART</a> page.</p>

        <p class="ms-cl-filter">
            <label class="screen-reader-text" for="ms-cl-area">Area</label>
            <select id="ms-cl-area">
                <option value="">All areas</option>
                <?php foreach ( $areas as $a => $n ) : ?><option value="<?php echo esc_attr( $a ); ?>"><?php echo esc_html( $a ); ?> (<?php echo (int) $n; ?>)</option><?php endforeach; ?>
            </select>
            <label class="screen-reader-text" for="ms-cl-sort">Sort by date</label>
            <select id="ms-cl-sort">
                <option value="new">Date: newest first</option>
                <option value="old">Date: oldest first</option>
            </select>
            <label class="screen-reader-text" for="ms-cl-q">Search</label>
            <input type="search" id="ms-cl-q" placeholder="Search" autocomplete="off">
            <span id="ms-cl-count" class="description"></span>
        </p>

        <?php foreach ( $months as $ym => $rows ) : ?>
            <div class="ms-cl-month">
                <h2><?php echo esc_html( date_i18n( 'F Y', strtotime( $ym . '-01' ) ) ); ?> <span class="ms-cl-n"><?php echo count( $rows ); ?></span></h2>
                <table class="widefat striped ms-cl-table">
                    <tbody>
                    <?php foreach ( $rows as $e ) : ?>
                        <tr data-area="<?php echo esc_attr( $e[1] ); ?>" data-text="<?php echo esc_attr( strtolower( $e[1] . ' ' . $e[2] ) ); ?>">
                            <td class="ms-cl-day"><?php echo esc_html( date_i18n( 'M j', strtotime( $e[0] ) ) ); ?></td>
                            <td class="ms-cl-area"><span class="ms-cl-chip"><?php echo esc_html( $e[1] ); ?></span></td>
                            <td class="ms-cl-text"><?php echo esc_html( $e[2] ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
        <p id="ms-cl-none" class="description" hidden>Nothing matches.</p>
    </div>

    <style>
        .ms-changelog .ms-cl-lead { max-width: 760px; color: #50575e; }
        .ms-cl-chip { display: inline-block; padding: 1px 9px; border: 1px solid #c3c4c7; border-radius: 999px; background: #fff; color: #50575e; font-size: 12px; font-weight: 400; line-height: 1.7; vertical-align: middle; white-space: nowrap; }
        .ms-cl-chip.is-on { border-color: #2271b1; background: #f0f6fc; color: #2271b1; }
        .ms-cl-filter { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin: 16px 0; }
        .ms-cl-filter input[type="search"] { width: 260px; max-width: 100%; }
        .ms-cl-month { max-width: 1000px; margin: 0 0 22px; }
        .ms-cl-month h2 { margin: 0 0 8px; font-size: 16px; }
        .ms-cl-n { color: #646970; font-weight: 400; font-size: 13px; }
        .ms-cl-table td { vertical-align: top; padding: 9px 12px; }
        .ms-cl-day { width: 58px; color: #646970; white-space: nowrap; }
        .ms-cl-area { width: 150px; }
        .ms-cl-text { line-height: 1.5; }
        @media (max-width: 782px) { .ms-cl-table td { display: block; padding: 3px 12px; } .ms-cl-day, .ms-cl-area { width: auto; } .ms-cl-table tr { display: block; padding: 8px 0; } }
    </style>
    <script>
    (function () {
        var area = document.getElementById('ms-cl-area'), q = document.getElementById('ms-cl-q'), count = document.getElementById('ms-cl-count'), none = document.getElementById('ms-cl-none');
        var rows = [].slice.call(document.querySelectorAll('.ms-cl-table tr')), months = [].slice.call(document.querySelectorAll('.ms-cl-month'));
        function apply() {
            var a = area.value, t = q.value.trim().toLowerCase(), shown = 0;
            rows.forEach(function (r) {
                var ok = (!a || r.getAttribute('data-area') === a) && (!t || r.getAttribute('data-text').indexOf(t) > -1);
                r.style.display = ok ? '' : 'none'; if (ok) shown++;
            });
            months.forEach(function (m) { m.style.display = m.querySelector('tr:not([style*="none"])') ? '' : 'none'; });
            count.textContent = (a || t) ? shown + ' of ' + rows.length : '';
            none.hidden = shown > 0;
        }
        var sort = document.getElementById('ms-cl-sort'), none_p = none.parentNode, newest = true;
        function flip(list) { list.reverse().forEach(function (el) { el.parentNode.appendChild(el); }); }
        sort.addEventListener('change', function () {
            var want = sort.value === 'new';
            if (want === newest) return;
            newest = want;
            months.reverse(); months.forEach(function (m) { none_p.insertBefore(m, none); });
            months.forEach(function (m) { flip([].slice.call(m.querySelectorAll('tbody tr'))); });
        });
        area.addEventListener('change', apply); q.addEventListener('input', apply);
    })();
    </script>
    <?php
}
