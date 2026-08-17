
define(['jquery', 'core/ajax', 'core/templates', 'core/str'], function($, Ajax, Templates, Str) {

    return /** @alias module:enrol_manual/form-potential-user-selector */ {

        processResults: function(selector, results) {
            var users = [];
            if ($.isArray(results)) {
                $.each(results, function(index, course) {
                    users.push({
                        value: course.id,
                        label: course.coursefullname
                    });
                });
                return users;

            } else {
                return results;
            }
        },

        transport: function(selector, query, success, failure) {
            const companyid = $(selector).data('companyid') || 0;
            var promise;
            var perpage = parseInt($(selector).attr('perpage'));
            if (isNaN(perpage)) {
                perpage = 100;
            }

            var makhoaidarr = [];
            var makhoaarr = [];
            var malopidarr = [];
            var maloparr = [];
            var useridarr = [];

            var onDocumentReady = false;

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
            // console.log(query);

            promise = Ajax.call([{
                methodname: 'local_thlib_get_courses',
                args: {
                    makhoaarr: makhoaarr,
                    maloparr: maloparr,
                    useridarr: useridarr,
                    time_from: null,
                    time_to: null,
                    search: query,
                    companyid: companyid
                }
            }]);

            promise[0].then(function(results) {
                //console.log(results);

                if (results.length <= perpage) {
                    success(results);
                    return;
                } else {
                    return Str.get_string('toomanycoursetoshow', 'local_thlib', '>' + perpage).then(function(toomanyuserstoshow) {
                        success(toomanyuserstoshow);
                        return;
                    });
                }

            }).fail(failure);
        }

    };

});
