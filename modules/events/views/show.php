<h1><?= $headline ?></h1>
<?= flashdata() ?>
<div class="card">
    <div class="card-heading">
        Event Details
    </div>
    <div class="card-body">
        <div class="text-right mb-3">
            <?= anchor($back_url, 'Back', array('class' => 'button alt')) ?>
            <?= anchor(BASE_URL.'events/create/'.$update_id, 'Edit', array('class' => 'button')) ?>
            <?= anchor('events/delete_conf/'.$update_id, 'Delete',  array('class' => 'button danger')) ?>
        </div>
        <div class="detail-grid">
            <div class="detail-row">
                <div class="detail-label">Event Title</div>
                <div class="detail-value"><?= out($event_title) ?></div>
            </div>
            <div class="detail-block">
                <div class="detail-label">Event Description</div>
                <div class="detail-content"><?= nl2br(out($event_description)) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Date And Time</div>
                <div class="detail-value"><?= out($date_and_time) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Important</div>
                <div class="detail-value"><?= out($important) ?></div>
            </div>
        </div>
    </div>
</div>
