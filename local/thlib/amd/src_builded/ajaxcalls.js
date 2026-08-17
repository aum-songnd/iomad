define(['jquery', 'core/notification', 'core/ajax'],
    function($, notification, ajax) {

        function Ajaxcall() {
            this.value = "ajax ok";
        };

        Ajaxcall.prototype.loadcourses = function(makhoaarr, maloparr, useridarr, time_from, time_to, onDocumentReady = false) {
            if (!makhoaarr || makhoaarr == '') makhoaarr = [];
            if (!maloparr || maloparr == '') maloparr = [];
            if (!useridarr || useridarr == '') useridarr = [];

            console.log(makhoaarr);
            console.log(maloparr);
            console.log(useridarr);

            var promises = ajax.call([{
                methodname: 'local_thlib_loadcourses',
                args: {
                    makhoaarr: makhoaarr,
                    maloparr: maloparr,
                    useridarr: useridarr,
                    time_from: time_from,
                    time_to: time_to
                },

                fail: notification.exception
            }]);

            args =  {
                makhoaarr: makhoaarr,
                maloparr: maloparr,
                useridarr: useridarr,
                time_from: time_from,
                time_to: time_to
            };

            console.log(args);

            $("#fitem_id_courseidarr input").attr("readonly", "true");
            promises[0].then(function(data) {
                console.log(data);

                var select_course = $("select#id_courseidarr");

                var datavalue = [];
                $.each(data, function(i, obj) {
                    datavalue.push(obj.id);
                });

                var optadded = [];
                $("select#id_courseidarr option").each(function(i, optel) {
                    if (i == 0) return;
                    if (onDocumentReady) {
                        var optvalue = parseInt($(optel).attr('value'));
                        if (!datavalue.includes(optvalue)) {
                            optel.remove();
                        } else {
                            optadded.push(optvalue);
                        }
                    } else {
                        optel.remove();
                    }
                });

                $.each(data, function(i, obj) {
                    var id = obj.id;
                    if (!optadded.includes(id)) {
                        select_course.append($('<option>', {
                            value: obj.id,
                            text: obj.coursefullname
                        }));
                    }
                });

                $("#fitem_id_courseidarr input").removeAttr("readonly", "true");
            });
        };

        return Ajaxcall;
    });