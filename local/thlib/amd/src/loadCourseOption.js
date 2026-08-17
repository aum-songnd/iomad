define(['jquery', 'local_thlib/ajaxcalls'],
    function($, Ajaxcalls) {

        function on_select_change_value(onDocumentReady = false) {

            if($('.myloader').length == 0)         // use this if you are using id to check
            {                                    
                var a = $('#fitem_id_courseidarr');
                a.before('<div class="myloader"></div>');
            }
            else
            {
                $('.myloader').show();
            }

            var data = $('.mform').serializeArray().reduce(function(obj, item) {
                obj[item.name] = item.value;
                var selector = "[name='" + item.name + "'] option:selected";
                obj[item.name + "_text"] = $(selector).text();
                return obj;
            }, {});

            var makhoa = data["makhoaid_text"];
            var malop = data["malopid_text"];
            var time_from_day = data["time_from[day]"];
            var time_from_month = data["time_from[month]"];
            var time_from_year = data["time_from[year]"];
            var time_to_day = data["time_to[day]"];
            var time_to_month = data["time_to[month]"];
            var time_to_year = data["time_to[year]"];
            var userid = data["userid_text"];

            // var time_from = new Date(time_from_year, time_from_month, time_from_day).getTime() / 1000;
            // var time_to = new Date(time_to_year, time_to_month, time_to_day).getTime() / 1000;
            
            var makhoaidarr = [];
            var makhoaarr = [];
            var malopidarr = [];
            var maloparr = [];
            var useridarr = [];

            var option = $('input[type=radio][name=show_option]:checked').val();

            if (option == 0){
                $('#fitem_id_makhoaid .form-autocomplete-selection > span[data-value]').each(function(index, element) {
                    makhoaidarr.push($(element).attr('data-value'));
                });

                $select = $('#fitem_id_makhoaid select');

                if (onDocumentReady) {
                    $select.find('[selected]').each(function(index, element) {
                        $text = $(element).text();
                        makhoaarr.push($text);
                    });
                }

                $.each(makhoaidarr, function(index, element) {
                    var text = $select.find(`option[value=${element}]`).text();
                    if (!makhoaarr.includes(text)) {
                        makhoaarr.push(text);
                    }
                });
            }else if (option == 1){
                $('#fitem_id_malopid .form-autocomplete-selection > span[data-value]').each(function(index, element) {
                    malopidarr.push($(element).attr('data-value'));
                });

                $select = $('#fitem_id_malopid select');
                if (onDocumentReady) {
                    $select.find('[selected]').each(function(index, element) {
                        $text = $(element).text();
                        maloparr.push($text);
                    });
                }

                $.each(malopidarr, function(index, element) {
                    var text = $select.find(`option[value=${element}]`).text();
                    if (!malopidarr.includes(text)) {
                        maloparr.push(text);
                    }
                });
            }else if (option == 2){
                $('#fitem_id_userid .form-autocomplete-selection > span[data-value]').each(function(index, element) {
                    useridarr.push($(element).attr('data-value'));
                });

                $select = $('#fitem_id_userid select');            
                if (onDocumentReady) {
                    $select.find('[selected]').each(function(index, element) {
                        $text = $(element).attr('value');
                        if (!useridarr.includes($text)) {
                            useridarr.push($text);
                        }
                    });
                }
            }

            // console.log(makhoaarr);
            // console.log(maloparr);
            // console.log(useridarr);

            // console.log(data);
            // console.log(makhoa);
            // console.log(malop);

            if (!userid || userid == '') userid = 0;
            var ajaxx = require("local_thlib/ajaxcalls");
            var ajax1 = new ajaxx();
            ajax1.loadcourses(makhoaarr, maloparr, useridarr, null, null, onDocumentReady).then(()=>{
                $('.myloader').hide();
            }); 

        }

        return {
            loadCourseOption: function() {
                $(document).ready(function() {
                    $('select.custom-select ').each(function(index, el) {

                        var el = $(el);
                        if ($(el).attr("id") == 'id_courseidarr') {
                            return;
                        }
                        $(el).on('change', function() {
                            $("#fitem_id_courseidarr .form-autocomplete-selection").html('<span class="mb-3 mr-1">No Selection</span>');
                            on_select_change_value();
                        });
                    });

                    $('#fgroup_id_show_option input.form-check-input').on('change', function() {
                        $("#fitem_id_courseidarr .form-autocomplete-selection").html('<span class="mb-3 mr-1">No Selection</span>');
                        on_select_change_value();
                    })

                    on_select_change_value(true);
                });
            },
        };
    });