
define(['jquery', 'core/ajax', 'core/templates', 'core/str'], function($, Ajax, Templates, Str) {

    return /** @alias module:enrol_manual/form-potential-user-selector */ {

        processResults: function(selector, results) {
            var users = [];
            if ($.isArray(results)) {
                $.each(results, function(index, malop) {
                    users.push({
                        value: malop.id,
                        label: malop.malop
                    });
                });
                return users;

            } else {
                return results;
            }
        },

        transport: function(selector, query, success, failure) {
            var promise;
            var perpage = parseInt($(selector).attr('perpage'));
            if (isNaN(perpage)) {
                perpage = 100;
            }

            promise = Ajax.call([{
                methodname: 'local_thlib_loadmalop',
                args: {
                    search: query,
                    // searchanywhere: true,
                    // page: 0,
                    // perpage: perpage + 1
                }
            }]);

            promise[0].then(function(results) {
                // console.log(results);

                if (results.length <= perpage) {
                    success(results);
                    return;
                } else {
                    return Str.get_string('toomanymaloptoshow', 'local_thlib', '>' + perpage).then(function(toomanymaloptoshow) {
                        success(toomanymaloptoshow);
                        return;
                    });
                }

            }).fail(failure);
        }

    };

});
