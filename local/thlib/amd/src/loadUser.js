
define(['jquery', 'core/ajax', 'core/templates', 'core/str'], function($, Ajax, Templates, Str) {

    return /** @alias module:enrol_manual/form-potential-user-selector */ {

        processResults: function(selector, results) {
            var users = [];
            if ($.isArray(results)) {
                $.each(results, function(index, user) {
                    users.push({
                        value: user.id,
                        label: user._label
                    });
                });
                return users;

            } else {
                return results;
            }
        },

        transport: function(selector, query, success, failure) {
            // Lấy companyid từ data attribute
            const companyid = $(selector).data('companyid') || 0;
            var promise;
            var userfields = $(selector).attr('userfields').split(',');
            var perpage = parseInt($(selector).attr('perpage'));
            if (isNaN(perpage)) {
                perpage = 100;
            }
          
            promise = Ajax.call([{
                methodname: 'local_thlib_loadusers',
                args: {
                    search: query,
                    companyid: companyid
                    // searchanywhere: true,
                    // page: 0,
                    // perpage: perpage + 1
                }
            }]);
            
            promise[0].then(function(results) {
               //console.log(results);
                var promises = [],
                i = 0;

                if (results.length <= 100) {
                    // Render the label.
                    $.each(results, function(index, user) {

                        var ctx = user,
                            identity = [];
                        $.each(userfields, function(i, k) {
                            if (typeof user[k] !== 'undefined' && user[k] !== '') {
                                ctx.hasidentity = true;
                                identity.push(user[k]);
                            }
                        });
                        ctx.identity = identity.join(', ');
                        promises.push(Templates.render('local_thlib/form-user-selector', ctx));
                    });
                   
                    // Apply the label to the results.
                    return $.when.apply($.when, promises).then(function() {
                        var args = arguments;
                        $.each(results, function(index, user) {
                            user._label = args[i];
                            i++;
                        });
                        success(results);
                        return;
                    });

                } else {
                    return Str.get_string('toomanyuserstoshow', 'core', '>' + 100).then(function(toomanyuserstoshow) {
                        success(toomanyuserstoshow);
                        return;
                    });
                }

            }).fail(failure);
        }

    };

});
