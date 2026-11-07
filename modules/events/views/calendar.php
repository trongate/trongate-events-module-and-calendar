<div id="calendar-app">
    <h1>Events Calendar</h1>

    <div class="cal-card">
        <div class="cal-toolbar">
            <div class="cal-nav">
                <?php
                $prev_attr = [
                    'class' => 'cal-nav-btn',
                    'title' => 'Previous month',
                    'aria-label' => 'Previous month',
                    'mx-get' => 'events/calendar/'.$prev_year.'/'.$prev_month,
                    'mx-target' => '#calendar-app',
                    'mx-swap' => 'outerHTML',
                    'mx-push-url' => 'true'
                ];
                echo anchor('#', '&#8249;', $prev_attr);
                ?>

                <div class="cal-month-label"><?= out($month_label) ?></div>

                <?php
                $next_attr = [
                    'class' => 'cal-nav-btn',
                    'title' => 'Next month',
                    'aria-label' => 'Next month',
                    'mx-get' => 'events/calendar/'.$next_year.'/'.$next_month,
                    'mx-target' => '#calendar-app',
                    'mx-swap' => 'outerHTML',
                    'mx-push-url' => 'true'
                ];
                echo anchor('#', '&#8250;', $next_attr);
                ?>
            </div>

            <?php
            if ($is_current_month === false) {
                $today_attr = [
                    'class' => 'button alt cal-today',
                    'mx-get' => 'events/calendar/'.$today_year.'/'.$today_month,
                    'mx-target' => '#calendar-app',
                    'mx-swap' => 'outerHTML',
                    'mx-push-url' => 'true'
                ];
                echo anchor('#', 'Today', $today_attr);
            }
            ?>
        </div>

        <div class="cal-weekdays">
            <?php foreach ($weekdays as $weekday): ?>
                <div><?= out($weekday) ?></div>
            <?php endforeach; ?>
        </div>

        <div class="cal-grid">
            <?php foreach ($weeks as $week): ?>
                <?php foreach ($week as $day): ?>
                    <?php
                    $cell_content = '<span class="cal-day-num">'.$day['day'].'</span>';
                    if ($day['event_summary'] !== '') {
                        $cell_content .= '<span class="cal-day-event">'.out($day['event_summary']).'</span>';
                    }

                    $empty_cell_attr = [
                        'class' => $day['css_class'],
                        'data-date' => $day['date'],
                        'title' => 'Add an event on '.$day['day_label'],
                        'aria-label' => 'Add an event on '.$day['day_label']
                    ];
                    ?>
                    <?php if ($day['has_events'] === true): ?>
                        <?php
                        $modal_options = [
                            'id' => 'day-events-modal',
                            'width' => '760px',
                            'modalHeading' => $day['day_label'],
                            'showCloseButton' => 'true'
                        ];
                        $cell_attr = ' mx-get="events/day_events/'.$day['date'].'"'
                            .' mx-build-modal="'.htmlspecialchars(json_encode($modal_options), ENT_QUOTES, 'UTF-8').'"';
                        ?>
                        <div class="<?= $day['css_class'] ?>" data-date="<?= out($day['date']) ?>"<?= $cell_attr ?>><?= $cell_content ?></div>
                    <?php else: ?>
                        <?= anchor('events/create/'.$day['date'], $cell_content, $empty_cell_attr) ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>
