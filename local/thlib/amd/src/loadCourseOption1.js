define(['jquery', 'local_thlib/ajaxcalls'],
    function($, Ajaxcalls) {

        return {
            loadCourseOption: function() {
                $(document).ready(function() {

                    $('select.custom-select ').each(function(index, el) {

                        var el = $(el);
                        if ($(el).attr("id") == 'id_courseidarr' || $(el).attr("id") == 'id_time_from_day'
                            || $(el).attr("id") == 'id_time_from_month' || $(el).attr("id") == 'id_time_from_year'
                            || $(el).attr("id") == 'id_time_to_day' || $(el).attr("id") == 'id_time_to_month'
                            || $(el).attr("id") == 'id_time_to_year') {
                            return;
                        }
                        $(el).on('change', function() {
                            $("#fitem_id_courseidarr .form-autocomplete-selection").html('<span class="mb-3 mr-1">No Selection</span>');
                            $("#id_courseidarr").empty();
                        });
                    });

                    $('#fgroup_id_show_option input.form-check-input').on('change', function() {
                        $("#fitem_id_courseidarr .form-autocomplete-selection").html('<span class="mb-3 mr-1">No Selection</span>');
                        $("#id_courseidarr").empty();
                    })
                });
            },
        };
    });
