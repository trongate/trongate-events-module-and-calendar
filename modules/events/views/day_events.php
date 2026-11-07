<?php
/**
 * Events for a single day.
 *
 * Rendered as a Trongate MX fragment into a dynamically built modal,
 * whenever a calendar day cell containing events is clicked.
 */
?>
<?php if (empty($rows)): ?>
    <p>There are no events on this day.</p>
<?php else: ?>
    <div class="table-container">
        <table class="records-table">
            <thead>
                <tr>
                    <th class="text-left">Event Title</th>
                    <th class="text-left">Date And Time</th>
                    <th class="text-left">Important</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= anchor('events/show/'.$row->id, out($row->event_title)) ?></td>
                        <td><?= out($row->date_and_time) ?></td>
                        <td><?= out($row->important) ?></td>
                        <td>
                            <div class="actions">
                                <a href="events/show/<?= $row->id ?>" class="button alt button-round" title="View event"><i class="tg tg-eye"></i></a>
                                <a href="events/create/<?= $row->id ?>" class="button alt button-round" title="Edit event"><i class="tg tg-pencil"></i></a>
                                <a href="events/delete_conf/<?= $row->id ?>" class="button alt button-round" title="Delete event"><i class="tg tg-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
