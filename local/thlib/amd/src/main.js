define([
	'jquery',
	'local_thlib/jszip',
	'local_thlib/jquery.dataTables',
	'local_thlib/dataTables.buttons',
	// 'local_thlib/buttons.flash',
	'local_thlib/pdfmake',
	// 'local_thlib/vfs_fonts',
	'local_thlib/buttons.html5',
	'local_thlib/buttons.print',
	], function($, jszip, DataTable, datatablebutton, /*buttonflash,*/ pdfmake, /*vfsfonts,*/ buttonhtml5, buttonprint) {

		window.JSZip = jszip;

		return {
			init: function(selector, title, language) {

				var linkToCss = $('link[href="https://cdn.datatables.net/1.11.3/css/jquery.dataTables.min.css"]');
				if(linkToCss.length===0)
				{
					var linkToCss = $('#my_custom_datatable_css');	
				}				

				if(linkToCss.length===0)
				{
					var csslink = '<link id="my_custom_datatable_css" rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.3/css/jquery.dataTables.min.css">';
					$("head").first().append(csslink);
					$('link[href="https://cdn.datatables.net/1.10.21/css/jquery.dataTables.min.css"]').remove();
				}


				$(document).ready(function() {
					var configure = {
						"lengthMenu": [
						[10, 25, 50, -1],
						[10, 25, 50, "All"]
						],
						"pageLength": 25,
						"dom": 'lBfrtip',
						"buttons": [{
							extend: 'copy',
							text: function(dt, button, config) {
								return dt.i18n('buttons.mcopy', 'Copy');
							},
							title: title
						}, {
							extend: 'csv',
							text: 'csv',
							title: title
						}, {
							extend: 'excel',
							text: 'excel',
							title: title,
							exportOptions: {
								columns: ':visible',
								format: {
									body: function(data, row, column, node) {
										data = $('<p>' + data + '</p>').text();
										$data = $.isNumeric(data.replace(',', '.')) ? data.replace(',', '.') : data;
										return $data;
									}
								}
							}
						}, {
							extend: 'pdf',
							text: 'pdf',
							title: title
						}, {
							extend: 'print',
							text: function(dt, button, config) {
								return dt.i18n('buttons.print', 'Print');
							},
							title: title
						}],
						"aaSorting": [],
					};

					if (language == 'vi') {
						configure.language = {
							"url": "//cdn.datatables.net/plug-ins/1.10.21/i18n/Vietnamese.json",
							buttons: {
								mcopy: "sao chép",
								print: "in",
							},
							"decimal": ","
						}
					}


					$(selector).DataTable(configure);
				});
			},

			scrollIntoView: function() {
				$(document).ready(function() {
					var element = document.querySelector("#mytextarea");
					element.scrollIntoView({
						behavior: 'smooth'
					});
				});
			},

			addAsteriskToCustomRequiredFieldForm: function(rootdomain, selector = '.custom_required') {
				$(document).ready(function() {
					$(selector).each(function(index, el) {
						var html1 = '<abbr class="initialism text-danger" title="Required" id="yui_3_17_2_1_1605836917092_308"><img class="icon " alt="Required" title="Required" src="' +
						rootdomain +
						'/theme/image.php/lambda/core/1605755554/req" id="yui_3_17_2_1_1605836917092_307"></abbr>';

						var html2 = '<div class="text-danger" title="Required" id="yui_3_17_2_1_1615155061716_803"> \
						<img class="icon " alt="Required" title="Required" src="'+rootdomain+'/theme/image.php/lambda/core/1615154550/req" id="yui_3_17_2_1_1615155061716_802">\
						</div>';

						var a = $(el).find('span.float-sm-right.text-nowrap').first();
						a.html(html1);

						var a = $(el).find('.ml-1.ml-md-auto.d-flex.align-items-center.align-self-start').first();
						a.html(html2);
					});
				});
			}
		};
	});