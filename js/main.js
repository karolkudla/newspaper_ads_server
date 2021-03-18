jQuery(function(){
  if(jQuery('.table_wrapper').is('.javascript')){
		
		function scroll() {
			jQuery(window).scroll(function() {
			   if(jQuery(window).scrollTop() + jQuery(window).height() > jQuery(document).height() - 10) {

					jQuery(window).unbind();
					var offset = jQuery(".offset").text()

					var medium_type = jQuery(".choose_medium_type option:selected").val();
					var naklad_sort = jQuery(".choose_naklad_sort option:selected").val();
					var choose_province = jQuery(".choose_province option:selected").val();
				   
					if (jQuery( ".no_results" ).length == 0) {
						filter_offset(medium_type,naklad_sort,choose_province,offset)
						setTimeout(function() {
							scroll();
							console.log("WŁĄCZAM SCROLLA")
						}, 1000);
					} else {
						console.log("WYŁĄCZAM SCROLL")
					
						/* WPISZ DO STORAGE ŻE SCROLL WYŁĄCZONY */
						/* PRZY KLIKNIECIU NA FILTRY SPRAWDZA CZY SCROLL WŁĄCZONY CZY NIE, 
						JEŚLI TAK TO WŁACZA GO Z POWROTEM */
						
						sessionStorage.setItem("scroll", "off");
					}
			   }
			});
		};

		function upload_product_image(button) {
					
			var formData = new FormData();
			var file = button.closest('form').find("input[type=file]").prop('files')[0];
			formData.append('image', file ); 	
			formData.append('action', 'upi');

			jQuery.ajax({
				url: global.ajax,
				type: 'POST',
				processData: false,
				contentType: false,
				data: formData,
				success: function(fileData) {
					
					ajax_add_to_cart_vg(fileData,button)

				},  
				error: function (response) {
					
					alert("Nie udało się wgrać tego obrazu.");
					console.log(response);
					
				}
			})
			
		}

		function show_added_to_cart_notice() {
			jQuery('.notices_wrapper').fadeIn()
			setTimeout(function() {
				jQuery('.notices_wrapper').fadeOut()
			},1000)
		}

		function ajax_add_to_cart_vg(fileData,button) {
			
			var day_date = button.parent().siblings('.publication_day_wrapper').find('.publication_day').val()
			var day_name = button.parent().siblings('.publication_day_wrapper').find('.day_name').text()
			
			jQuery.ajax({
					url: global.ajax,
					type: 'POST',
					data: { 
						'action': 'variation_to_cart',
						'product_id': button.closest(".type-product").attr("pid"),
						'quantity': 1,
						'vid'   : button.siblings(".variation_id").val(),
						'var'   : button.closest("form").find("select").attr("data-attribute_name"),
						'cart_item_data' : { 
							'wccpf_obraz' : {
								'fee_rules': {},
								'fname': "obraz",
								'format': "",
								'ftype':"file",
								'pricing_rules':{},
								'user_val': {
									'file': fileData['file'],
									'url': fileData['url'],
									'type': fileData['type']
								}
							},
							'data_wydania' : day_name +" "+day_date
						}
						
					},
					success: function(response) {
									
						console.log(response)
									
						if (response == 'false') {
							alert("Posiadasz już tą opcję w koszyku. Wybierz inną opcję lub inne miejsce reklamowe.")
						} else {
							button.closest('.td_form').css('box-shadow','inset -5px 0px 0px 0px #8bc34a')
							button.closest('.td_form').append('<div class="added_to_cart_notice">Dodano do koszyka. <a href="https://gazeta.wokulski.online/koszyk/">Zobacz koszyk</a></div>')
						}

						jQuery(document.body).trigger('wc_fragment_refresh');
					}
				}) 
			
		}

		function filter(medium_type,naklad_sort,choose_province) {
				
			var scrollo = sessionStorage.getItem('scroll')
			console.log(scrollo)
			if (scrollo == 'off') {
				scroll()
				sessionStorage.setItem('scroll','on')
				console.log("Właczam scrolla na nowo")
			}
				
			jQuery( ".product, .no_results" ).remove()
			
			jQuery.ajax({
				type : "POST",
				url : global.ajax,
				data : {
					action: "filter_data",
					medium_type:medium_type,
					naklad_sort:naklad_sort,
					choose_province:choose_province
				},
				success: function(response) {
			
					jQuery( ".vg_filters" ).after( response );

					var new_offset = jQuery( ".type-product" ).length
					jQuery('.offset').html(new_offset)
					
					jQuery.getScript("https://gazeta.wokulski.online/wp-content/plugins/woocommerce/assets/js/frontend/single-product.min.js");
					jQuery.getScript("https://gazeta.wokulski.online/wp-content/plugins/woocommerce/assets/js/frontend/add-to-cart-variation.min.js");

					return false;	
					
				}
			});  
			
		}

		function filter_offset(medium_type,naklad_sort,choose_province,offset) {
			
			jQuery.ajax({
				type : "POST",
				url : global.ajax,
				data : {
					action: "filter_data",
					medium_type:medium_type,
					naklad_sort:naklad_sort,
					choose_province:choose_province,
					offset:offset
				},
				success: function(response) {
			
					jQuery( ".table_ajax > tbody" ).append( response ) 

					var new_offset = jQuery( ".type-product" ).length
					jQuery('.offset').html(new_offset)

					jQuery.getScript("https://gazeta.wokulski.online/wp-content/plugins/woocommerce/assets/js/frontend/single-product.min.js");
					jQuery.getScript("https://gazeta.wokulski.online/wp-content/plugins/woocommerce/assets/js/frontend/add-to-cart-variation.min.js");
					
					return false;	
					
				}
			});  
			
		}

		jQuery(document).ready(function() {

			scroll()
			sessionStorage.setItem("scroll","on");

			var medium_type = "";
			var naklad_sort = "";
			var choose_province = "";
			filter(medium_type,naklad_sort,choose_province)

			jQuery("body").on("click", ".woocommerce-variation-add-to-cart-disabled .disabled", function(e) {
				alert('Wybierz opcję reklamy przed dodaniem jej do koszyka.');
			})

			jQuery("body").on("click", ".woocommerce-variation-add-to-cart-enabled button", function(e) {
				e.preventDefault();
				
				var file = jQuery(this).closest('form').find("input[type=file]")[0].files[0];	
				if (file === undefined) {
					
					alert("Dodaj obraz przed dodaniem go do koszyka.")
					
				} else {	

					var button = jQuery(this)
					upload_product_image(button);

				}
			})
						   
			jQuery(".choose_medium_type").change(function() {
				
				var medium_type = jQuery(".choose_medium_type option:selected").val();
				var naklad_sort = jQuery(".choose_naklad_sort option:selected").val();
				var choose_province = jQuery(".choose_province option:selected").val();
				
				if (jQuery(this).val() !== '') {
					jQuery(this).css('box-shadow', '0 0px 8px 0px #2196F3')
				} else {
					jQuery(this).css('box-shadow', 'none')
				}
				
				filter(medium_type,naklad_sort,choose_province)
			})
			
			jQuery(".choose_province").change(function() {
						
				var medium_type = jQuery(".choose_medium_type option:selected").val();
				var naklad_sort = jQuery(".choose_naklad_sort option:selected").val();
				var choose_province = jQuery(".choose_province option:selected").val();
				
				if (jQuery(this).val() !== '') {
					jQuery(this).css('box-shadow', '0 0px 8px 0px #2196F3')
				} else {
					jQuery(this).css('box-shadow', 'none')
				}
				
				filter(medium_type,naklad_sort,choose_province)
			})
			
			jQuery(".choose_naklad_sort").change(function() {
						
				var medium_type = jQuery(".choose_medium_type option:selected").val();
				var naklad_sort = jQuery(".choose_naklad_sort option:selected").val();
				var choose_province = jQuery(".choose_province option:selected").val();
				
				if (jQuery(this).val() !== '') {
					jQuery(this).css('box-shadow', '0 0px 8px 0px #2196F3')
				} else {
					jQuery(this).css('box-shadow', 'none')
				}
				
				filter(medium_type,naklad_sort,choose_province)
			})
			
			var $loading = jQuery('.spinner_wrapper').hide();
			jQuery(document)
			  .ajaxStart(function () {
				$loading.show();
			  })
			  .ajaxStop(function () {
				$loading.hide();
			  });

		})
		
		jQuery("body").on("mouseenter", ".read_more_about_redaction", function(e) {
			jQuery(this).next(".read_more_about_redaction_data").fadeIn();
		})
		
		jQuery("body").on("mouseleave", ".read_more_about_redaction", function(e) {
			jQuery(this).next(".read_more_about_redaction_data").fadeOut();
		})
		
		jQuery("body").on("mouseenter", ".mouse_over_to_image", function(e) {
			jQuery(this).next(".hidden_list_img").fadeIn();
		})
		
		jQuery("body").on("mouseleave", ".hidden_list_img", function(e) {
			jQuery(this).fadeOut();
		})
		
  } else {
	  console.log("NIEODPOWIEDNIA STRONA")
  }
});

