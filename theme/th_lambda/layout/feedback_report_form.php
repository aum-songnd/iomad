<div class="th_lambda_feedback_report">
    <div id="errorFormContainer" style="display:none;">
        <form id="errorForm" action="<?php echo $CFG->wwwroot ?>/local/th_feedback_report/contact.php" method="post">
            <button type="button" class="close" aria-label="Close" id="closeButton">
                <span aria-hidden="true">&times;</span>
            </button>
            <h2><?php echo get_string('title_log_feedback_report', 'theme_th_lambda'); ?></h2>
            <div class="error_img">
                <img id="screenshotImage" alt="Screenshot" style="max-width: 100%;"/>
            </div>
            <textarea id="screenshotData" name="screenshotData" rows="10" cols="30" style="display:none;"></textarea>
            <input id="currentUrl" name="currentUrl" rows="10" cols="30" style="display:none;"></input>
            <input id="browserName" name="browserName" type="hidden">
            <input id="osName" name="osName" type="hidden">
            
            <div class="feedback-category">
                <label><?php echo get_string('feedback_type', 'theme_th_lambda'); ?></label>
                <select id="supportType" name="supportType" onchange="showSubcategories_Error(this.value)" required>
                    <option value=""><?php echo get_string('select_feedback_type', 'theme_th_lambda'); ?></option>
                    <?php
                    global $DB;
                    $categories = $DB->get_records('th_error_categories');
                    foreach ($categories as $category) {
                        if ($category->parentid == null) {
                            echo '<option value="' . $category->id . '">' . $category->name . '</option>';
                        }
                    }
                    ?>
                </select>
                <?php
                foreach ($categories as $category) {
                    if ($category->parentid == null) {
                        $subcategories = $DB->get_records('th_error_categories', array('parentid' => $category->id));
                        if (!empty($subcategories)) {
                            echo '<select id="subcate_'.$category->id.'" name="subcate_'.$category->id.'" style="display: none;" required>';
                            echo '<option value="">'. get_string('select_feedback_type', 'theme_th_lambda') .'</option>';
                            foreach ($subcategories as $subcategory) {
                                echo '<option value="' . $subcategory->id . '">' . $subcategory->name . '</option>';
                            }
                            echo '</select>';
                        }
                    }
                }
                ?>
            </div>
            <div style="width: 100%; display: flex;">
                <textarea id="issueDescription" name="issueDescription" rows="5" placeholder="<?php echo get_string('issue_description_placeholder', 'theme_th_lambda'); ?>" required minlength="60"></textarea>
            </div>
            <input type="file" accept="image/*" id="uploadScreenshot" style="display:none;">
            <div class="feedback_buttons">
                <input type="submit" id="submit_feedback" value="<?php echo get_string('btn_feedback', 'theme_th_lambda'); ?>">
            </div>
        </form>
    </div>
    <div class="error-button" >
        <img src="<?php echo $CFG->wwwroot ?>/theme/th_lambda/img/feedback.png" alt="error-button" width="36" height="36">
        <div class="feedback_text"><?php echo get_string('feedback_text', 'theme_th_lambda') ?></div>
    </div>
</div>
<script>
    function showSubcategories_Error(categoryId) {
        document.querySelectorAll('select[id^="subcate_"]').forEach(function(select) {
            if (select.id === 'subcate_' + categoryId) {
                select.style.display = '';
                select.required = true;
                select.selectedIndex = 0; // Reset to the first option
            } else {
                select.style.display = 'none';
                select.required = false;
                select.selectedIndex = 0;
            }
        });
    }

    document.addEventListener('submit', function(event) { 
        var submitButton = document.getElementById('submit_feedback');
        submitButton.disabled = true;
    });
</script>
