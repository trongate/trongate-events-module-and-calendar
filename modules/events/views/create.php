<h1><?= $headline ?></h1>
<?= validation_errors() ?>
<div class="card">
    <div class="card-heading">
        Event Details
    </div>
    <div class="card-body">
        <?php
        echo form_open($form_location);

        echo form_label('Event Title');
        $event_title_attr = [
            'placeholder' => 'Enter Event Title',
            'required'  => true,
            'autocomplete' => 'off',
            'minlength' => 2,
            'maxlength' => 255
        ];
        echo form_input('event_title', $event_title, $event_title_attr);

        echo form_label('Event Description');
        $event_description_attr = [
            'placeholder' => 'Enter Event Description',
            'minlength' => 2,
            'rows' => 12
        ];
        echo form_textarea('event_description', $event_description, $event_description_attr);

        echo form_label('Date And Time');
        $date_and_time_attr = [
            'placeholder' => 'Enter Date And Time',
            'required'  => true
        ];
        echo form_datetime_local('date_and_time', $date_and_time, $date_and_time_attr);

        echo '<label>';
        echo form_checkbox('important', 1, $important);
        echo ' Important';
        echo '</label>';

        echo '<div class="text-center">';
        echo anchor($cancel_url, 'Cancel', ['class' => 'button alt']);
        echo form_submit('submit', 'Submit');
        echo '</div>';
        
        echo form_close();
        ?>
    </div>
</div>
