var myScroll,fixScroll,tm=false,top_btn=false,back1,back2,back=0,keyTO,debug,debug_input,debug_xhr,debug_result,hu=HOME_URL,map,sll=false,zm=false,slat,slong,scrollOff=false,hasTouch='ontouchstart' in window,tbtm=false,iOS=true,taplock=false,tlTO,lastgn,ff=false,perListPage=20,geoLoc=false,defaultSort='wishlist',extras,//lTO,
	// pixrat=(window.devicePixelRatio == 1.5 ? .75 : 1),
	pixrat=(navigator.userAgent.match(/ADR6300/i) ? .75 : 1),
	backHelper=function(){}
	fixFooter=function(){
		if(!iOS){
			var scrollTop = document.body.scrollTop || document.documentElement.scrollTop;
			if(scrollTop == 0 && jQuery("#scroll-wrapper").height() > jQuery("body").height()){
				jQuery("#footer").css("display","none");
				setTimeout('window.scrollTo(0,1);setTimeout("fixFooter();",50);',250);
			} else {
				jQuery("#footer").css({"position":"absolute","top":(Math.ceil(scrollTop*pixrat) + jQuery(window).height())+"px","bottom":"auto","display":"block"});
				// alert(scrollTop);
			}
		}
	},
	fixScrollWrapper=function(){
		if(!iOS){
			jQuery("#scroll-wrapper").css({"min-height":(jQuery(window).height()-125)+"px","padding-bottom":"50px"});
		}
	},
	refreshiScroll = function(){
		window.scrollTo(0,1);
		if(iOS){
			if(scrollOff)
				myScroll.disable();
			else
				myScroll.enable();
			myScroll.scrollTo(0,0,0);
			setTimeout(function(){myScroll.refresh();}, 5);
		} else {
			fixFooter();
		}
	},
	hideStuff=function(){
		if(!iOS)
			jQuery("#content").add(".search-bar").add("ul.submenu").hide();
	},
	showStuff=function(){
		var to = 250;
		if(iOS) to = 0;
		setTimeout('jQuery("#content").add(".search-bar").add("ul.submenu").show();refreshiScroll();', to);
	},
	showMore=function(page){
		jQuery(".list-page-"+page).removeClass("hidden");
		page++;
		if(jQuery(".list-page-"+page).length > 0)
			jQuery("#show_more").attr("onclick", "showMore("+page+");");
		else
			jQuery("#show_more").remove();
	},
	loadLocationAndReverse=function(elt){
		geoLoc = false;
		if(navigator.geolocation) {
			navigator.geolocation.getCurrentPosition(function(position) {
				geoLoc = position.coords;
				if(elt) {
					var eltp = elt.parent(),
						geocoder = new google.maps.Geocoder(),
						latlng = new google.maps.LatLng(position.coords.latitude,position.coords.longitude);
					elt.val("current location");
					eltp.children("span").addClass("explanation-hidden");
					eltp.append("<input type='hidden' name='latitude' value='"+position.coords.latitude+"' />");
					eltp.append("<input type='hidden' name='longitude' value='"+position.coords.longitude+"' />");
					geocoder.geocode({'latLng': latlng}, function(results, status) {
						if (status == google.maps.GeocoderStatus.OK) {
							if (results[0]) {
								elt.val(results[0].formatted_address);
							}
						}
					});
				}
			}, function() {});
			return true;
		}
		return false;
	},
	loadLocation=function(callback, failureCallback){
		if(geoLoc){
			if(callback) callback(geoLoc);
			return true;
		}
		geoLoc = false;
		if(navigator.geolocation) {
			navigator.geolocation.getCurrentPosition(function(position) {
				geoLoc = position.coords;
				if(callback) callback(geoLoc);
			}, function() {
				if(failureCallback) failureCallback();
			});
			return true;
		}
		return false;
	},
	addToMap = function(map,lat,lng,boxpost,description,type,input){
		var marker = new google.maps.Marker({
			position: new google.maps.LatLng(lat,lng),
			title:description
		});

		marker.setMap(map);
		google.maps.event.addListener(marker, 'click', function(){
			data = {action: "fc_load_find", subaction: "box_detail", box_post: boxpost};
			get_wp_data('GET', data);
			backHelper=function(){
				sll = map.getCenter();
				zm = map.getZoom();
				get_wp_data(type,input);
			};
		});
	},
	addBackButton = function(){
		if(jQuery("div#back-button").length == 0)
			jQuery("#scroll-wrapper").before('<div id="back-button"><input type="submit" name="back" value="Back"></div>');
		jQuery("div#container").addClass("with-back-button");
	},
	animateNotice = function(height){
		var pfn = jQuery("p.finalized-notice");
		pfn.animate({"height":height,"padding":"4px"},300);
		setTimeout(function(){
			pfn.animate({"height":0},256,function(){pfn.animate({"padding":0},44,function(){
				pfn.remove()
				// pfn.hide()
			})})
		}, 5000);
	},
	get_wp_data_with_loc = function(type, data){
		get_wp_data(type, data);
		if(geoLoc){
			console.log(geoLoc);
			data.latitude = geoLoc.latitude;
			data.longitude = geoLoc.longitude;
			get_wp_data(type, data);
		} else if(!loadLocation(
				function(coords){
					console.log(coords);
					data.latitude = coords.latitude;
					data.longitude = coords.longitude;
					get_wp_data(type, data);
				},
				function(){ console.log("no location"); get_wp_data(type, data); }
		)) {
			get_wp_data(type, data);
		}
	},
	get_wp_data = function(type, input, tries){
		if(tries == undefined) tries = 0;
		backHelper=function(){};
		input.timestamp=(new Date).getTime();
		debug_input = input;
		lastgn = input.good_name;
		jQuery("body").addClass("loading-ajax");
		// function rotate(elt, count) {
		// 	if (count==360) { count = 0 }
		// 	elt.style.WebkitTransform = 'scale(0.4) rotate('+count+'deg)';
		// 	lTO=window.setTimeout(function(){rotate(elt, count+45)}, 100);
		// }
		// lTO=window.setTimeout(function(){rotate(document.getElementById('spinner'), 0)}, 100);
		jQuery.ajax({
			url: ADMIN_AJAX_URL,
			type: type,
			data: input,
			error: function(xhr, status, err){
				// console.log(status);
				// console.log(err);
				if(tries > 5){
					jQuery("body").removeClass("loading-ajax");
					// window.clearTimeout(lTO);
					// do something intelligent
					alert("I had trouble communicating with the server. Wait a bit and then try again.");
				} else {
					get_wp_data(type, input, tries + 1);
				}
			},
			success: function(data, status, xhr){
				if(jQuery(data).find("redirect").text() != "")
					window.location = jQuery(data).find("redirect").text();
				fixFooter();
				debug = data;
				debug_result = status;
				debug_xhr = xhr;
				jQuery("body").removeClass("loading-ajax");
				jQuery("div#top-buttons").removeClass("homemenu page");
				// console.log(jQuery("div#info-button").attr("class"));

				// window.clearTimeout(lTO);
				var d = jQuery(data),
					contentDiv = jQuery("div#content"),
					sw = jQuery("#scroll-wrapper"),
					err = d.find("error"),
					errp = jQuery("p.error-message"),
					msg = d.find("message").text(),
					navf = jQuery("#nav-menu a[title=find]"),
					navg = jQuery("#nav-menu a[title=give]"),
					navb = jQuery("#nav-menu a[title=basket]"),
					navp = jQuery("#nav-menu a[title=profile]"),
					gn = input.good_name,
					loc = input.location,
					showMap = (input.sort_by == "map"),
					boxen = d.find("box"), box = boxen,
					gooden = d.find("good"),
					resen = d.find("reservation"), res = resen,
					mapnearby = "nearby",
					showLine = false,
					hidden = "",
					profileExtras = "",
					counter = 0,
					listpage = 0,
					i = 0,
					form, button, msgs, gu,
					newHTML, newBoxHTML, pic,
					zoom, center, lat, lng,
					instr, desc, affl, lourl, xtras,
					qstr, exact, rID, bp, bn, u,
					activity, user, profilepic, comments,
					un, id, fn, ln, em, bi, af, ad, sr, no;
				hideStuff();


				// FIND FUNCTIONS
				if(input.action == "fc_load_find") {


					// DISPLAY LIST OF AVAILABLE BOXES
					if(input.subaction == "get_list"){
						if(input.good_name == lastgn){
							back = 0;
							contentDiv.html("");
							jQuery("#back-button").remove();;
							if(!navf.is(".current")){
								jQuery("#nav-menu a").removeClass("current");
								navf.addClass("current");
							}
							if(!jQuery("body").is(".with-submenu-and-bar")) {
								if(input.sort_by == "map"){
									// input.sort_by = "nearby";
									mapnearby = "nearby";
								} else if(input.sort_by == "nearby"){
									// input.sort_by = "map";
									mapnearby = "map";
								}
								jQuery("div.search-bar").remove();
								jQuery("ul.submenu").remove();
								jQuery("div#scroll-wrapper").before('<div id="good-type-search" class="find search-bar"><form action="'+hu+'/find/" method="post"><input type="text" name="good_name" value=""><input type="submit" value="Search"></form></div>');
								newHTML = '<ul class="submenu"><li id="sort-by-wishlist"';
								if(input.sort_by == "wishlist")
									newHTML += ' class="current"';
								newHTML += '><a href="'+hu+'/find/wishlist/">Wishlist</a></li><li id="sort-by-recent"';
								if(input.sort_by == "recent")
									newHTML += ' class="current"';
								newHTML += '><a href="'+hu+'/find/recent/">Recent</a></li><li id="sort-by-'+mapnearby+'"';
								if(input.sort_by == "map" || input.sort_by == "nearby")
									newHTML += ' class="current"';
								newHTML += '><a href="'+hu+'/find/'+mapnearby+'/">Nearby/Map</a></li></ul>';
								jQuery("div#scroll-wrapper").before(newHTML);
								jQuery("body").addClass("with-bar with-submenu with-submenu-and-bar");
							}
							if(input.clean_slate == "true")
								jQuery("div#good-type-search input[name=good_name]").val("");
							// jQuery("ul.submenu li").removeClass("current");
							// jQuery("ul.submenu li#sort-by-"+input.sort_by).addClass("current");
							if(input.note == "show") {
								contentDiv.append("<p class='finalized-notice'>It's all yours! Visit your basket when you're ready to pick it up.</p>");
								animateNotice("2.5em");
							}
							if(showMap){
								slat = d.find("search_location").find("latitude").text();
								slong = d.find("search_location").find("longitude").text();
								if(slat == "" || slong == ""){
									slat = 37.8318660;
									slong = -122.2824592;
								}
								contentDiv.append("<div id='mapdiv'></div>");
								jQuery("div#mapdiv").css("height", (jQuery(window).height() - 185) + "px");
								if(sll) center = sll;
								else center = new google.maps.LatLng(slat,slong);
								if(zm) zoom = zm;
								else zoom = 14;
								map = new google.maps.Map(document.getElementById("mapdiv"), {
									zoom: zoom,
									center: center,
									mapTypeId: google.maps.MapTypeId.ROADMAP
								});
								zm = false;
								sll = false;
								scrollOff = true;
							}
							if(boxen.length > 0){
								boxen.each(function(){
									listpage = Math.floor(counter/perListPage);
									if(counter%perListPage > 0 && !showMap) contentDiv.append('<p class="separating-line list-page-'+listpage+hidden+'"></p>');
									// if(showLine && !showMap) contentDiv.append('<p class="separating-line"></p>');
									showLine = true;
									var th = jQuery(this), desc = th.find("good").find("name").text()+': '+th.find("quantity_str").text(),
										bp = th.children("id").text(),
										newBoxHTML = '<div class="box-o-goods find-box list-page-'+listpage+hidden+'"><div class="box-pic">'+th.find('pic').text()+'</div><div class="box-info"><form action="'+hu+'/find/" method="post"><input type="hidden" name="searched" value="'+input.good_name+'"><input type="hidden" name="box_post" value="'+bp+'"><input type="hidden" name="action" value="box_detail"><p>'+desc+'<br>'+th.find("location_str").text()+'<span>'+th.find("distance").text()+' mi</span> <input type="submit" value="claim"></p></form></div></div>';
									if(showMap){
										addToMap(
											map,
											th.find("latitude").text(),
											th.find("longitude").text(),
											bp, desc, type, input
										);
									} else {
										contentDiv.append(newBoxHTML);
										counter++;
										if(counter >= perListPage)
											hidden = " hidden";
									}
								});
								if(!showMap && boxen.length > perListPage)
									contentDiv.append("<p class='separating-line'</p><p><a id='show_more' onclick='showMore(1);'>Show More</a></p>");
							} else if(gooden.length > 0){	
								// no boxes matched, but some known goods did
								contentDiv.append("<p class='notice'>Nobody's sharing anything like that right now. You can try adding a good to your wishlist to make it easier to find the next time someone shares some.</p>")
								d.find("good").each(function(){
									var good = jQuery(this),
										good_name = good.find("name").text(),
										good_type = good.find("id").text(),
										newGoodHTML = '<div class="box-o-goods find-good-box"><div class="box-pic">'+good.find('pic').text()+'</div><div class="box-info"><form action="'+hu+'/find/" id="wishlist_form_'+good_type+'" method="post"><input type="hidden" name="action" value="';
									if(good.find('in_wishlist').text() == "false")
										newGoodHTML += 'add_to_wishlist"><input type="hidden" name="good_type" value="'+good_type+'"><p>'+good_name+'<span>+</span><input id="add_to_wishlist" type="submit" value="+"></p></form></div></div>';
									else
										newGoodHTML += 'remove_from_wishlist"><input type="hidden" name="good_type" value="'+good_type+'"><p>'+good_name+'<span>-</span><input id="remove_from_wishlist" type="submit" value="-"></p></form></div></div>';
									contentDiv.append(newGoodHTML);
								});
							} else {
								// no boxes or goods matched
								if(input.sort_by == "wishlist"){
									if(d.find("no_wishlist").text() == "true")
										contentDiv.append("<p class='notice'>There’s nothing on your wishlist right now. You can add a good to your wishlist to make it easier to find the next time someone shares that item.</p>");
									else if(d.find("empty_wishlist").text() == "true")
										contentDiv.append("<p class='notice'>Nobody's sharing anything on your wishlist right now. Check out what people have shared recently or nearby instead.</p>");
								}
							}
						} else {
							// ignore result of older queries, only show the latest
						}
					}


					// DISPLAY DETAILS OF THE GIVEN BOX
					else if(input.subaction == "box_detail") {
						back = 1;
						back1 = {bar: jQuery("div.search-bar").clone(), submenu: jQuery("ul.submenu").clone(), contentHTML: jQuery("#content").html()};
						addBackButton();
						instr = box.find('instructions').text();
						affl = box.find('affiliation').text();
						jQuery("div.search-bar").remove();
						jQuery("ul.submenu").remove();
						jQuery("body").removeClass("with-bar with-submenu with-submenu-and-bar");
						newBoxHTML = '<div class="box-to-claim"><div class="box-pic">'+box.find('pic').text()+'</div>';
						if(box.find('in_wishlist').text() == "false")
							newBoxHTML += '<form action="'+hu+'/find/" id="wishlist_form" method="post"><input type="hidden" name="action" value="add_to_wishlist"><input type="hidden" name="box_post" value="'+box.find('id').text()+'"><input type="hidden" name="good_type" value="'+box.find('good_type').text()+'"><input id="add_to_wishlist" type="submit" value="Add '+box.find('good_name').text()+' to wishlist"></form>';
						else
							newBoxHTML += '<form action="'+hu+'/find/" id="wishlist_form" method="post"><input type="hidden" name="action" value="remove_from_wishlist"><input type="hidden" name="box_post" value="'+box.find('id').text()+'"><input type="hidden" name="good_type" value="'+box.find('good_type').text()+'"><input id="remove_from_wishlist" type="submit" value="Remove '+box.find('good_name').text()+' from wishlist"></form>';
						newBoxHTML += '<div class="box-info"><p>'+box.find('good_name').text()+': '+(box.find('quantity_str').text())+'</p><p>Given by '+box.find('giver').text();
						if(affl += "")
							newBoxHTML += " via " + affl;
						newBoxHTML += '</p></div><div class="box-info"><h3>Pickup location</h3><p>'+box.find('location').text()+'</p>';
						if(instr != "")
							newBoxHTML += '<h3>Instructions</h3><p>'+instr+'</p>';
						newBoxHTML += '</div><div class="take-it"><form action="'+hu+'/find/" method="post"><input type="hidden" name="box_post" value="'+box.find('id').text()+'"><input type="hidden" name="action" value="finalize"><input type="hidden" name="available_quantity" value="'+box.find('quantity').text()+'"><p class="label">Quantity</p><p><span class="explanation">How many are you taking?</span><input type="number" id="claim_quantity" name="claim_quantity" value=""></p><input id="reserve_lot" type="submit" value="Reserve this lot"></form></div>';
						newBoxHTML += '<br><div id="comments">';
						box.find("comment").each(function(){
							newBoxHTML += "<p class='comment'><strong>"+jQuery(this).find("user").text()+"</strong> says \""+jQuery(this).find("content").text()+"\"</p>";
						});
						newBoxHTML += '</div></div>';
						contentDiv.html(newBoxHTML);
					}


					// UPDATE SCREEN TO TURN "ADD" BUTTON INTO "REMOVE" BUTTON, ETC.
					else if(input.subaction == "add_to_wishlist") {
						if(d.find("added").text() == "true"){
							form = jQuery("form#wishlist_form");
							if(form.length > 0) {
								button = form.find("input#add_to_wishlist");
								if(button.length > 0)
									button.attr("value",button.attr("value").replace("Add","Remove").replace(" to wishlist"," from wishlist"));
							} else {
								form = jQuery("form#wishlist_form_"+input.good_type);
								button = form.find("input#add_to_wishlist");
								if(button.length > 0)
									button.attr("value","-");
							}
							form.find("input[name=action]").attr("value","remove_from_wishlist");
							form.find("span").replaceWith("<span>-</span>");
							if(button.length > 0)
								button.attr("id","remove_from_wishlist");
						}
					}


					// UPDATE SCREEN TO TURN "REMOVE" BUTTON INTO "ADD" BUTTON, ETC.
					else if(input.subaction == "remove_from_wishlist") {
						if(d.find("removed").text() == "true"){
							form = jQuery("form#wishlist_form");
							if(form.length > 0) {
								button = form.find("input#remove_from_wishlist");
								if(button.length > 0)
									button.attr("value",button.attr("value").replace("Remove","Add").replace(" from wishlist"," to wishlist"));
							} else {
								form = jQuery("form#wishlist_form_"+input.good_type);
								button = form.find("input#remove_from_wishlist");
								if(button.length > 0)
									button.attr("value","+");
							}
							form.find("input[name=action]").attr("value","add_to_wishlist");
							form.find("span").replaceWith("<span>+</span>");
							if(button.length > 0)
								button.attr("id","add_to_wishlist");
						}
					}


					// IF FAILED TO FINALIZE RESERVATION, SHOW ERROR MESSAGE. ELSE REFRESH LIST.
					else if(input.subaction == "reserve") {
						if(err.length == 0){
							data = {action: "fc_load_find", subaction: "get_list", note: "show", sort_by: "wishlist", clean_slate: "true"};
							get_wp_data('GET', data);
						} else {
							if(errp.length == 0) {
								jQuery("input#reserve_lot").before("<p class='error-message'></p>");
								errp = jQuery("p.error-message");
							}
							errp.html(msg);
						}
					}
				}


				// BASKET FUNCTIONS
				else if(input.action == "fc_load_basket"){


					// DISPLAY LIST OF OPEN RESERVATIONS
					if(input.subaction == "get_list"){
						back = 0;
						contentDiv.html("");
						jQuery("div.search-bar").remove();
						jQuery("ul.submenu").remove();
						jQuery("#back-button").remove();
						jQuery("body").removeClass("with-bar with-submenu with-submenu-and-bar");
						if(input.note == "taken"){
							contentDiv.append("<p class='finalized-notice'>You got it!</p>");
							animateNotice("1.25em");
						}
						if(input.note == "released"){
							contentDiv.append("<p class='finalized-notice'>Your reservation has been canceled.</p>");
							animateNotice("1.25em");
						}
						if(input.note == "flagged"){
							contentDiv.append("<p class='finalized-notice'>The box has been flagged.</p>");
							animateNotice("1.25em");
						}
						if(!navb.is(".current")){
							jQuery("#nav-menu a").removeClass("current");
							navb.addClass("current");
						}
						if(jQuery("div#pickup-notice").length == 0)
							contentDiv.prepend("<div id='pickup-notice'><h2>ITEMS THAT I NEED TO PICK UP</h2><p class='basket_count'>"+resen.length+"</p></div>");
						if(resen.length > 0){
							resen.each(function(){
								if(showLine) contentDiv.append('<p class="separating-line"></p>');
								showLine = true;
								var res = jQuery(this), newBoxHTML = '<div class="box-o-goods basket-box"><div class="box-pic">'+res.find('pic').text()+'</div><div class="box-info"><form action="'+hu+'/basket/" method="post"><input type="hidden" name="reservation" value="'+res.find("res_ID").text()+'" /><input type="hidden" name="box_post" value="'+res.find("box").children("id").text()+'"><input type="hidden" name="action" value="take"><p>'+res.find("box").find("good_name").text()+': '+res.find("quantity_str").text()+'<br>'+res.find("location_str").text()+res.find("expiration").text()+'<input type="submit" value="Take"></p></form></div></div>';
								contentDiv.append(newBoxHTML);
							});
						}
					}


					// DISPLAY DETAILED INFO FOR GIVEN RESERVATION
					else if(input.subaction == "get_detail") {
						back = 1;
						back1 = {contentHTML: jQuery("#content").html(), submenu: false, bar: false};
						addBackButton();
						instr = res.find('instructions').text();
						rID = res.find("id").text();
						jQuery("div#pickup-notice").remove();
						jQuery("body").removeClass("with-bar with-submenu with-submenu-and-bar");
						newBoxHTML = '<div class="box-to-claim"><div class="box-pic">'+res.find('pic').text()+'</div><div class="box-info right"><p>'+res.find('good_name').text()+': '+res.find('quantity_str').text()+'</p><p>Given by '+res.find('giver').text()+'</p></div><div class="box-info"><h3>Pickup location</h3><p>'+res.find('location').text()+'</p>';
						if(instr != "")
							newBoxHTML += '<h3>Instructions</h3><p>'+instr+'</p>';
						newBoxHTML += '</div><div class="pickup"><form action="'+hu+'/basket/" method="post"><input type="hidden" name="reservation" value="'+rID+'"><input type="hidden" name="claim_quantity" value="'+res.find('quantity').text()+'"><input type="hidden" name="action" value="finalize"><input type="hidden" name="box_post" value="'+res.find('box_post').text()+'">'+res.find("quantity_input").text()+'<p>Once you see the items, please enter the code to check them out.</p><input type="number" class="box-number" size="5" name="box_number" min="0" max="99999" step="1" value=""><input type="submit" id="take_lot" value="I\'m taking this!"></form></div><form action="'+hu+'/basket/" id="reservation-release-form" method="post"><input type="hidden" name="reservation" value="'+rID+'"><input type="hidden" name="action" value="release"><input type="submit" value="Release these goods."></form><form action="'+hu+'/basket/" id="reservation-problem-form" method="post"><p class="label">Something wrong?</p><input type="hidden" name="reservation" value="'+rID+'" /><input type="hidden" name="action" value="flag" /><p><input type="radio" name="problem" value="missing" />Nothing here</p><p><input type="radio" name="problem" value="rotten" />The items are rotten</p><p><input type="radio" name="problem" value="other" onchange="if(jQuery(\'input:radio[name=problem]:checked\').val()==\'other\'){jQuery(\'#other_problem\').removeAttr(\'disabled\');jQuery(\'#other_problem\').focus()}else jQuery(\'#other_problem\').attr(\'disabled\',\'disabled\');" /><span class="explanation">Other</span><input type="text" name="other_problem" id="other_problem" disabled="disabled" /></p><input type="submit" value="Flag this box" /></form></div>';
						contentDiv.html(newBoxHTML);
					}


					// IF FAILED TO FINALIZE RESERVATION, SHOW ERROR MESSAGE. ELSE REFRESH LIST.
					else if(input.subaction == "take") {
						if(err.length == 0){
							data = {action: "fc_load_basket", subaction: "get_list", note: "taken"};
							get_wp_data('GET', data);
						} else {
							if(errp.length == 0) {
								jQuery("input#take_lot").before("<p class='error-message'></p>");
								errp = jQuery("p.error-message");
							}
							errp.html(msg);
						}
					}


					// IF FAILED TO RELEASE RESERVATION, SHOW ERROR MESSAGE. ELSE REFRESH LIST.
					else if(input.subaction == "release") {
						if(err.length == 0){
							data = {action: "fc_load_basket", subaction: "get_list", note: "released"};
							get_wp_data('GET', data);
						} else {
							if(errp.length == 0) {
								jQuery("input#take_lot").before("<p class='error-message'></p>");
								errp = jQuery("p.error-message");
							}
							errp.html(msg);
						}
					}


					// IF FAILED TO FLAG BOX, SHOW ERROR MESSAGE. ELSE REFRESH LIST.
					else if(input.subaction == "flag") {
						if(err.length == 0){
							data = {action: "fc_load_basket", subaction: "get_list", note: "flagged"};
							get_wp_data('GET', data);
						} else {
							if(errp.length == 0) {
								jQuery("input#other_problem").after("<p class='error-message'></p>");
								errp = jQuery("p.error-message");
							}
							errp.html(msg);
						}
					}
				}


				// GIVE FUNCTIONS
				else if(input.action == "fc_load_give") {


					// DISPLAY LIST OF AVAILABLE GOOD TYPES
					if(input.subaction == "get_list"){
						exact = d.find('exact').text();
						back = 0;
						contentDiv.html("");
						jQuery("#back-button").remove();
						if(!navg.is(".current")){
							jQuery("#nav-menu a").removeClass("current");
							navg.addClass("current");
						}
						if(!jQuery("body").is(".with-bar") || jQuery("body").is(".with-submenu")){
							jQuery("div.search-bar").remove();
							jQuery("ul.submenu").remove();
							jQuery("div#scroll-wrapper").before('<div id="good-type-search" class="give search-bar"><form action="'+hu+'/give/" method="post"><input type="text" name="good_name" value=""><input type="submit" value="Search"></form></div>');
							jQuery("body").addClass("with-bar").removeClass("with-submenu with-submenu-and-bar");
						}
						if(input.clean_slate == "true")
							jQuery("div#good-type-search input[name=good_name]").val("");
						if(input.note == "show") {
							contentDiv.append("<p class='finalized-notice'>Box shared.<br />Got anything else to share?</p>");
							animateNotice("2.5em");
						}
						if(exact == 'false' && gn != undefined && gn != '') {
							contentDiv.append('<div id="create-good-type"><form action="'+hu+'/give/" method="post"><input type="hidden" name="good_name" value="'+gn+'" /><input type="hidden" name="action" value="create_good_type" /><input type="submit" value=\'Add "'+gn+'"\' /></form></div>');
						}
						if(gooden.length > 0){
							gooden.each(function(){
								listpage = Math.floor(counter/perListPage);
								if(counter%perListPage > 0) contentDiv.append('<p class="separating-line list-page-'+listpage+hidden+'"></p>');
								showLine = true;
								newBoxHTML = '<div class="box-o-goods give-box list-page-'+listpage+hidden+'"><div class="box-pic">'+jQuery(this).find('pic').text()+'</div><div class="box-info"><form action="'+hu+'/give/" method="post" class="good-type-list"><input type="hidden" name="good_type_post" value="'+jQuery(this).find('good_type').text()+'" /><input type="hidden" name="good_name" value="'+jQuery(this).find("name").text()+'"><input type="hidden" name="action" value="box_details"><p>'+jQuery(this).find("name").text()+'	<input type="submit" value=""></p></form></div></div>';
								contentDiv.append(newBoxHTML);
								counter++;
								if(counter >= perListPage)
									hidden = " hidden";
							});
						}	
						if(gooden.length > perListPage)
							contentDiv.append("<p class='separating-line'</p><p><a id='show_more' onclick='showMore(1);'>Show More</a></p>");
					}


					// CREATE A NEW BOX
					else if(input.subaction == "create_box_post"){
						lat = d.find("lat").text();
						lng = d.find("long").text();
						pic = d.find("pic").text();
						bp = d.find("post").text();
						bn = d.find("box_number").text();
						instr = input.instructions;
						desc = input.description;
						qstr = d.find("quantity_str").text();
						newHTML = '<div id="added-box"><div id="box-pic">'+pic+'</div><div id="box-info"><p id="box-quantity">'+gn+': '+qstr+'</p><p class="strong">Pickup location</p><p id="box-location">'+loc+'</p><p style="text-align:center;"><a href="http://maps.google.com/maps?q='+loc+'&amp;ll='+lat+','+lng+'" target="_blank"><img src="http://maps.google.com/maps/api/staticmap?center='+lat+','+lng+'&amp;zoom=15&amp;size=300x150&amp;maptype=roadmap&amp;markers='+lat+','+lng+'&amp;sensor=true" width="300" height="150"></a></p></div>';
						extras = d.find("extra");
						if(instr != "")
							newHTML += '<div id="box-instructions"><p class="strong">Instructions</p><p>'+instr+'</p></div>';
						if(desc != "")
							newHTML += '<div id="box-description"><p class="strong">Description</p><p>'+desc+'</p></div>';
						newHTML += '<hr><div id="what-to-do"><p>Write this number on your container of goods, then press share so that they can be publicly listed.</p><p id="box-number">'+bn+'</p><form action="'+hu+'/give/" method="post">';
						extras.each(function(){
							xtra = jQuery(this).find("name").text();
							newHTML += '<p><input style="display:inline;width:auto;margin:4px;" type="checkbox" name="'+xtra+'" value="yes" />'+jQuery(this).find("label").text()+'</p>';
						});
						newHTML += '<input type="hidden" name="box_post" value="'+bp+'" /><input type="hidden" name="action" value="finalize" /><p><input id="share_it" type="submit" value="Share It" /></p></form></div></div>';
						back2 = {contentHTML: contentDiv.html(), submenu: false, bar: false};
						contentDiv.html(newHTML);
					}


					// IF COULDN'T SHARE BOX, SHOW ERROR MESSAGE. ELSE RELOAD GOODS LIST.
					else if(input.subaction == "finish_sharing"){
						if(err.length == 0){
							data = {action: "fc_load_give", subaction: "get_list", note: "show", clean_slate: "true"};
							get_wp_data('GET', data);
						} else {
							if(errp.length == 0) {
								jQuery("input#share_it").before("<p class='error-message'></p>");
								errp = jQuery("p.error-message");
							}
							errp.html(msg);
						}
					}


					// CREATE NEW GOOD TYPE
					else if(input.subaction == "create_new_good_type"){
						if(err.length == 0){
							back = 2;
							back2 = {contentHTML: jQuery("#content").html(), bar: false, submenu: false};
							gt = d.find("post").text();
							jQuery("div.search-bar").remove();
							jQuery("body").removeClass("with-bar");
							gu = input.good_units;
							jQuery("#content").html('<div id="add-box"><form action="'+hu+'/give/" method="post"><p class="note strong">Giving '+gn+':</p><p class="label">Quantity Units</p><p><select id="good_units" name="good_units" onchange="if(this.value == \'other\'){jQuery(\'.show-for-other\').show();jQuery(\'input.show-for-other\').focus();}else jQuery(\'.show-for-other\').hide();"><option value="">'+gn+'</option><option value="oz">oz(s)</option><option value="lb">lb(s)</option><option value="bag">bag(s)</option><option value="box">box(es)</option><option value="can">can(s)</option><option value="jar">jar(s)</option><option value="other">other</option></select></p><p><span class="explanation show-for-other" style="display:none;">How do you count this good?</span><input type="text" class="show-for-other" style="display:none;" id="good_units_other" name="good_units_other" value="'+gu+'" /></p><p class="label">Quantity</p><p><span class="explanation">How much have you got?</span><input id="quantity" type="number" name="quantity" value="" /></p><p class="label">Address<br /></p><p><span class="explanation explanation-small">What is the drop off address? (street, city)</span><input type="text" id="location" name="location" value="" /></p><p class="label">Pickup Instructions</p><p><span class="explanation explanation-small">How do I find it? (e.g. behind the front desk)</span><input id="instructions" type="text" name="instructions" value="" /></p><p class="label">Description</p><p><span class="explanation">What\'s special about your goods?</span><input type="text" id="description" name="description" value="" /></p><input type="hidden" name="good_name" value="'+gn+'" /><input type="hidden" name="good_type_post" value="'+gt+'" /><input type="hidden" name="action" value="confirm" /><input class="submit" type="submit" name="create_box" value="Continue" /></form></div>');
							loadLocationAndReverse(jQuery("input#location"));
						} else {
							if(errp.length == 0) {
								jQuery("input#share_it").before("<p class='error-message'></p>");
								errp = jQuery("p.error-message");
							}
							errp.html(msg);
						}
					}
				}



				// PROFILE FUNCTIONS
				else if(input.action == "fc_load_profile") {
					u = d.find("user");


					// DISPLAY USER INFO
					if(input.subaction == "get_info"){
						back = 0;
						jQuery("body").removeClass("with-bar with-submenu with-submenu-and-bar");
						jQuery("#back-button").remove();
						jQuery("div.search-bar").remove();
						jQuery("ul.submenu").remove();
						boxen = d.find("shared").find("box");
						resen = d.find("foraged").find("res");
						activity = d.find("activity").find("box");
						profilepic = d.find("profile_pic").text();
						lat = u.find("latitude").text();
						lng = u.find("longitude").text();
						pic = TEMPLATE_URL+'/img/person.png';
						if(!navp.is(".current")){
							jQuery("#nav-menu a").removeClass("current");
							navp.addClass("current");
						}
						if(profilepic != "")
							pic = profilepic;
						contentDiv.html("");
						sw.before('<form action="'+hu+'/profile/" method="post" class="top-right-button"><input type="hidden" name="action" value="edit"><input id="edit_profile" type="submit" value="Settings"></form>');
						newHTML = '<div id="user-info"><div id="user-pic"><img src="'+pic+'" width="44" height="44" alt="person" title="person"></div><h3>'+u.find("display_name").text()+'</h3><p class="user-bio">'+u.find("bio").text()+'</p>';
						newHTML += '<h1>History</h1><div id="user-history">';
						if(activity.length == 0) {
							newHTML += "<p>Nothing yet.</p>";
						} else {
							activity.each(function(){
								var box = jQuery(this);
								// if(showLine) newHTML += '<p class="separating-line"></p>';
								// showLine = true;
								if(box.find("post_type").text() == "fcboxes"){
									newHTML += '<div class="box-o-goods profile-shared-box'+showLine+'"><div class="box-pic">'+box.find("pic").text()+'</div><div class="box-info"><form action="'+hu+'/profile/" method="post"><input type="hidden" name="box_post" value="'+box.find("box_post").text()+'"><input type="hidden" name="action" value="edit_shared"><p>'+box.find("good_name").text()+': '+box.find("quantity_str").text()+'<br />'+box.find("status_msg").text()+'<br /><span class="history-date">Given '+box.find("date_str").text()+'</span> <input type="submit" value="Detail" \></p></form></div></div>';
								} else {
									newHTML += '<div class="box-o-goods foraged-box'+showLine+'"><div class="box-pic">'+box.find("pic").text()+'</div><div class="box-info"><form action="'+hu+'/profile/" method="post"><input type="hidden" name="reservation" value="'+box.find("reservation").text()+'"><input type="hidden" name="claim_quantity" value="'+box.find("claim_quantity").text()+'"><input type="hidden" name="action" value="take"><input type="hidden" name="box_post" value="'+box.find("box_post").text()+'"><p>'+box.find("good_name").text()+': '+box.find("quantity_str").text()+'<br />'+box.find("status_msg").text()+'<br /><span class="history-date">Foraged '+box.find("date_str").text()+'</span> <input type="submit" value="Comment" \></p></form></div></div>';
								}
								showLine = " line-above";
							});
						}
						newHTML += "</div>";
						newHTML += '</div></div>';
						contentDiv.html(newHTML);
						if(input.note == "removed"){
							contentDiv.prepend("<p class='finalized-notice'>Okay, your box has been taken off the list.</p>");
							animateNotice("2.5em");
						}
						if(input.note == "updated"){
							contentDiv.prepend("<p class='finalized-notice'>Changes saved.</p>");
							animateNotice("1.25em");
						}
					}


					// VIEW/MODIFY A SHARED BOX
					else if(input.subaction == "edit_shared" || input.subaction == "edit_foraged"){
						back = 1;
						// console.log(input);
						back1 = {contentHTML: contentDiv.html(), submenu: false, bar: false, rightbtn: jQuery(".top-right-button").clone().wrap('<div></div>').parent().html(), leftbtn: jQuery(".top-left-button").clone().wrap('<div></div>').parent().html()};
						addBackButton();
						jQuery(".top-right-button").add(".top-left-button").remove();
						bn = box.find("box_number").text();
						gn = box.find("good_name").text();
						qstr = box.find("quantity_str").text();
						loc = box.find("location").text();
						lat = box.find("latitude").text();
						lng = box.find("longitude").text();
						instr = box.find("instructions").text();
						desc = box.find("description").text();
						pic = box.find("pic").text();
						comments = box.find("comment");
						bp = input.box_post;
						newHTML = '<div id="shared-box"><div id="box-pic">'+pic+'</div><div id="box-info"><p id="box-quantity">'+gn+': '+qstr+'</p><p class="strong">Pickup location</p><p id="box-location">'+loc+'</p>';
						if(lat != '' && lng != '')
							newHTML += '<p style="text-align:center;"><a href="http://maps.google.com/maps?q='+loc+'&ll='+lat+','+lng+'" target="_blank"><img width="300" height="150" src="http://maps.google.com/maps/api/staticmap?center='+lat+','+lng+'&zoom=15&size=300x150&maptype=roadmap&markers='+lat+','+lng+'&sensor=true"></a></p>';
						newHTML += '</div>';
						if(instr != '')
							newHTML += '<div id="box-instructions"><p class="strong">Instructions</p><p>'+instr+'</p></div>';
						if(desc != '')
							newHTML += '<div id="box-description"><p class="strong">Description</p><p>'+desc+'</p></div>';
						if(input.subaction == "edit_shared") newHTML += '<div id="what-to-do"><p class="strong">Box number</p><p id="box-number">'+bn+'</p></div>';
						newHTML += '<br><div id="comments">';
						comments.each(function(){
							newHTML += "<p class='comment'><strong>"+jQuery(this).find("user").text()+"</strong> says \""+jQuery(this).find("content").text()+"\"</p>";
						});
						newHTML += '</div><form action="'+hu+'/profile/" method="post" id="comment_form"><input type="hidden" name="box_post" value="'+bp+'" /><input type="hidden" name="old_action" value="'+input.subaction+'" /><input type="hidden" name="action" value="comment" /><p><textarea name="comment_content" id="comment_content" rows="3" cols="30"></textarea></p><p><input id="post_comment" type="submit" value="Add Comment" /></p></form>';
						if(input.subaction == "edit_shared")
							newHTML += '<form action="'+hu+'/profile/" method="post"><input type="hidden" name="box_post" value="'+bp+'" /><input type="hidden" name="action" value="remove_box" /><p><input id="remove_shared_box" type="submit" value="Remove this box" /></p></form>';
						newHTML += '</div>';
						contentDiv.html(newHTML);
					}

					// ADD A COMMENT
					else if(input.subaction == "add_comment"){
						user = u.text();
						if(user){
							jQuery("div#comments").prepend("<p class='comment'><strong>"+user+"</strong> says \""+input.comment_content+"\"</p>");
							jQuery("textarea[name=comment_content]").val("");
						}
					}


					// IF COULD NOT REMOVE SHARED BOX, SHOW ERROR MESSAGE. ELSE REFRESH LIST.
					else if(input.subaction == "remove_shared"){
						if(err.length == 0){
							data = {action: "fc_load_profile", subaction: "get_info", note: "removed"};
							get_wp_data('GET', data);
						} else {
							if(errp.length == 0) {
								jQuery("input#remove_shared_box").before("<p class='error-message'></p>");
								errp = jQuery("p.error-message");
							}
							errp.html(msg);
						}
					}


					// DISPLAY EDIT FORM
					else if(input.subaction == "edit_info"){
						if(input.from != "home") {
							back = 1;
							back1 = {contentHTML: contentDiv.html(), submenu: false, bar: false, rightbtn: jQuery(".top-right-button").clone().wrap('<div></div>').parent().html(), leftbtn: jQuery(".top-left-button").clone().wrap('<div></div>').parent().html()};
							addBackButton();
						}
						jQuery(".top-right-button").add(".top-left-button").remove();
						un = u.find("username").text();
						id = u.find("id").text();
						fn = u.find("first_name").text();
						ln = u.find("last_name").text();
						em = u.find("email").text();
						bi = u.find("bio").text();
						af = u.find("affiliation").text();
						ad = u.find("address").text();
						sr = u.find("search_radius").text();
						no = u.find("nonce").text();
						lourl = d.find("logout_url").text();
						xtras = u.find("extra");
						xtras.each(function(){
							profileExtras += jQuery(this).text();
						});
						contentDiv.html('<div id="user-info"><div id="logout"><p><a href="'+lourl+'">Logout</a></p></div><form id="your-profile" method="post" action="'+HOME_URL+'/profile/" class="profile-form">'+no+'<table class="form-table"><tbody><tr><th><label for="user_login">Username</label></th><td><input type="text" name="user_login_cannot_change" id="user_login_cannot_change" value="'+un+'" disabled="disabled" class="regular-text"></td></tr><tr><th><label for="first_name">First Name</label></th><td><input type="text" name="first_name" id="first_name" value="'+fn+'" class="regular-text"></td></tr><tr><th><label for="last_name">Last Name</label></th><td><input type="text" name="last_name" id="last_name" value="'+ln+'" class="regular-text"></td></tr><tr><th><label for="email">E-mail <span class="description">(required)</span></label></th><td><input type="text" name="email" id="email" value="'+em+'" class="regular-text"></td></tr><tr><th><label for="address">Default Location</label></th><td><input type="text" name="address" id="address" value="'+ad+'" class="regular-text"></td></tr><tr><th><label for="search_radius">Default Search Radius</label></th><td><input type="text" name="search_radius" id="search_radius" value="'+sr+'" class="regular-text"><span class="description">(in miles)</span></td></tr><tr><th><label for="description">Biographical Info</label></th><td><textarea name="description" id="description" rows="5" cols="30">'+bi+'</textarea> <span class="description">Share a little biographical information to fill out your profile. This will be shown publicly.</span></td></tr><tr><th><label for="affiliation">Affiliation</label></th><td><input type="text" name="affiliation" id="affiliation" value="'+af+'" class="regular-text"> <span class="description">If you are affiliated with a particular foraging group (e.g. Forage Oakland), you can note that here.</span></td></tr><tr id="password"><th><label for="pass1">New Password</label></th><td><input type="password" name="pass1" id="pass1" size="16" value="" autocomplete="off"> <span class="description">If you would like to change your password, type a new one. Otherwise leave this blank.</span><br><input type="password" name="pass2" id="pass2" size="16" value="" autocomplete="off"> <span class="description">Type your new password again.</span></td></tr>'+profileExtras+'</tbody></table><input type="hidden" name="from" value="profile"><input type="hidden" name="action" value="update"><input type="hidden" name="user_id" id="user_id" value="'+id+'"><p class="submit"><input type="submit" id="save_profile_edits" class="button-primary" value="Update Profile"></p></form><form id="cancel_profile_edit" method="post" action="'+HOME_URL+'/profile/" class="profile-form"><p class="submit"><input type="submit" class="button-primary" value="Cancel"></p></form></div>');
					}


					// SAVE PROFILE INFO
					else if(input.subaction == "save_edits"){
						if(err.length == 0){
							data = {action: "fc_load_profile", subaction: "get_info", note: "updated"};
							get_wp_data('GET', data);
						} else {
							msgs = d.find("message");
							ln = msgs.length;
							i = 0;
							msg = "";
							msgs.each(function(){
								msg += jQuery(this).text();
								if(i < ln)
									msg += "<br />";
								i++;
							});
							if(errp.length == 0) {
								jQuery("input#save_profile_edits").parent().before("<p class='error-message'></p>");
								errp = jQuery("p.error-message");
							}
							errp.html(msg);
						}
					}
				}


				// else {
				// 	console.log(data);
				// }

				// TELL ISCROLL WHAT'S WHAT
				if(err.length == 0)
					showStuff();
				scrollOff = false;
			}
		});
	},
	claimTapLock = function(caller){
		// console.log(caller);
		// console.log("taplock is "+taplock);
		if(caller == window) { // ignore
			return true;
		}// else alert("TRYING");
		if(taplock){
			// alert("DENIED!");
			return false;
		} else {
			taplock=true;
			// console.log("set taplock to true");
			setTimeout('taplock=false', 50);
			return true
		}
	},
	initialize = function(){
		var sm=jQuery("ul.submenu"),
			sb=jQuery("div.search-bar"),
			bd=jQuery("body"),
			bb=jQuery("div#back-button"),
			sw=jQuery("#scroll-wrapper"),
			clte = "click",
			showGive, showFind, showBasket, showProfile;

		if(hasTouch) clte = "touchend";

		if(bb.length>0){
			bb.insertBefore(sw);
		}
		if(sb.length>0){
			sb.insertBefore(sw);
			bd.addClass("with-bar")
		}
		if(sm.length>0){
			sm.insertBefore(sw);
			bd.addClass("with-submenu")
			if(sb.length>0)
				bd.addClass("with-submenu-and-bar")
		}
		if(window.navigator.standalone){
			bd.addClass("fullscreen");
		}
		jQuery("body").delegate("input", "focus", function(){
			clearTimeout(fixScroll);
			jQuery(this).siblings("span.explanation").addClass("explanation-hidden")
		});
		jQuery("body").delegate("input", "blur", function(){
			fixScroll=setTimeout(function(){window.scrollTo(0,1)},100);
			if(this.value=="")
				jQuery(this).siblings("span.explanation").removeClass("explanation-hidden")
		});


		/* try to make tapping on form buttons work inside iScroll pane */
		sw.delegate("input[type=submit]", "touchstart", function(e){tm=false});
		sw.delegate("input[type=submit]", "touchmove", function(e){tm=true});
		sw.delegate("input[type=submit]", "click", function(e){e.preventDefault();e.stopPropagation();return false});
		sw.delegate("input[type=submit]", clte, function(e, undefined){
			// console.log(e.type+" on "+e.target.outerHTML+" @ "+e.timeStamp);
			// if(jQuery(this).is("form.profile-form input"))
			// 	jQuery(this).parents("form").first().submit();
			// else if(!tm && !jQuery(this).is("form.profile-form input")){
			if(claimTapLock(this) && !tm){
				e.preventDefault();
				var t = jQuery(this), form = t.parents("form").first(), contentDiv = jQuery("div#content"), errMsg = "",
					bn, bp, cq, gn, gt, prob, gu, guo, guov, inst, instv, desc, descv, q, qv, l, lv, la, lav, lo, lov, em, gi, gq, xtraNames, xtraValues;
				if(t.is("div.find-box div.box-info input")){
					data = {action: "fc_load_find", subaction: "box_detail", box_post: form.find("input[name=box_post]").val()};
					get_wp_data('GET', data);
				} else if(t.is("input#add_to_wishlist")){
					data = {action: "fc_load_find", subaction: "add_to_wishlist", good_type: form.find("input[name=good_type]").val(), box_post: form.find("input[name=box_post]").val()}
					get_wp_data('POST', data);
				} else if(t.is("input#remove_from_wishlist")){
					data = {action: "fc_load_find", subaction: "remove_from_wishlist", good_type: form.find("input[name=good_type]").val(), box_post: form.find("input[name=box_post]").val()}
					get_wp_data('POST', data);
				} else if(t.is("input#reserve_lot")){
					bp = form.find("input[name=box_post]").val();
					cq = form.find("input[name=claim_quantity]").val();
					if(cq != ""){
						data = {action: "fc_load_find", subaction: "reserve", box_post: bp, claim_quantity: cq}
						get_wp_data('POST', data);
					}
				} else if(t.is("div.basket-box div.box-info input")){
					data = {action: "fc_load_basket", subaction: "get_detail", reservation: form.find("input[name=reservation]").val()};
					get_wp_data('GET', data);
				} else if(t.is("div.box-to-claim div.pickup input")){
					bn = form.find("input[name=box_number]").val();
					data = {action: "fc_load_basket", subaction: "take", reservation: form.find("input[name=reservation]").val(), taking_quantity: form.find("input[name=taking_quantity]").val(), box_number: bn};
					get_wp_data('POST', data);
				} else if(t.is("form#reservation-release-form input")){
					data = {action: "fc_load_basket", subaction: "release", reservation: form.find("input[name=reservation]").val()};
					get_wp_data('POST', data);
				} else if(t.is("form#reservation-problem-form input")){
					prob = form.find("input:radio[name=problem]:checked").val();
					if(prob){
						data = {action: "fc_load_basket", subaction: "flag", reservation: form.find("input[name=reservation]").val(), problem: prob, other_problem: form.find("input[name=other_problem]").val()};
						get_wp_data('POST', data);
					}
				} else if(t.is("div.give-box div.box-info input")){
					back = 1;
					back1 = {bar: jQuery("div.search-bar").clone(), contentHTML: jQuery("#content").html(), submenu: false};
					addBackButton();
					gn = form.find("input[name=good_name]").val();
					gt = form.find("input[name=good_type_post]").val();
					jQuery("div.search-bar").remove();
					jQuery("body").removeClass("with-bar");
					hideStuff();
					jQuery("#content").html('<div id="add-box"><form action="'+hu+'/give/" method="post"><p class="note strong">Giving '+gn+':</p><p class="label">Quantity Units</p><p><select id="good_units" name="good_units" onchange="if(this.value == \'other\'){jQuery(\'.show-for-other\').show();jQuery(\'input.show-for-other\').focus();}else jQuery(\'.show-for-other\').hide();"><option value="">'+gn+'</option><option value="oz">oz(s)</option><option value="lb">lb(s)</option><option value="bag">bag(s)</option><option value="box">box(es)</option><option value="can">can(s)</option><option value="jar">jar(s)</option><option value="other">other</option></select></p><p><span class="explanation show-for-other" style="display:none;">How do you count this good?</span><input type="text" class="show-for-other" style="display:none;" id="good_units_other" name="good_units_other" value="" /></p><p class="label">Quantity</p><p><span class="explanation">How much have you got?</span><input id="quantity" type="number" name="quantity" value="" /></p><p class="label">Address<br /></p><p><span class="explanation explanation-small">What is the drop off address? (street, city)</span><input type="text" id="location" name="location" value="" /></p><p class="label">Pickup Instructions</p><p><span class="explanation explanation-small">How do I find it? (e.g. behind the front desk)</span><input id="instructions" type="text" name="instructions" value="" /></p><p class="label">Description</p><p><span class="explanation">What\'s special about your goods?</span><input type="text" id="description" name="description" value="" /></p><input type="hidden" name="good_name" value="'+gn+'" /><input type="hidden" name="good_type_post" value="'+gt+'" /><input type="hidden" name="action" value="confirm" /><input class="submit" type="submit" name="create_box" value="Continue" /></form></div>');
					loadLocationAndReverse(jQuery("input#location"));
					showStuff();
				} else if(t.is("div#add-box input")){	
					gu = form.find("select[name=good_units]");
					guo = form.find("input[name=good_units_other]");
					guov = guo.val();
					inst = form.find("input[name=instructions]");
					instv = inst.val();
					desc = form.find("input[name=description]");
					descv = desc.val();
					q = form.find("input[name=quantity]");
					qv = q.val();
					l = form.find("input[name=location]");
					lv = l.val();
					la = form.find("input[name=latitude]");
					lav = la.val();
					lo = form.find("input[name=longitude]");
					lov = lo.val();
					em = "1.25em";
					if(qv == "" || typeof qv == "undefined")
						errMsg = "Quantity is required.";
					if(lav == "" || lov == "" || typeof lav == "undefined" || typeof lov == "undefined") {
						lav = "";
						lov = "";
					}
					if(lv == "" || typeof lv == "undefined")
						lv = lav + "," + lov;
					if(lv == ",") {
						if(errMsg == "") {
							errMsg = "Drop off address is required.";
						} else {
							errMsg += "<br />Drop off address is required.";
							em = "2.5em";
						}
					}
					if(errMsg == "") {
						data = {
							action: "fc_load_give", subaction: "create_box_post",
							good_name: form.find("input[name=good_name]").val(),
							good_units: gu.val(),
							good_units_other: guov,
							quantity: qv,
							location: lv,
							latitude: lav,
							longitude: lov,
							instructions: instv,
							description: descv,
							good_type_post: form.find("input[name=good_type_post]").val()
						};
						gu.find("option:selected").attr("selected","selected");
						guo.attr("value",guov);
						q.attr("value",qv);
						l.attr("value",lv);
						la.attr("value",lav);
						lo.attr("value",lov);
						inst.attr("value",instv);
						desc.attr("value",descv);
						back = 2;
						back2 = {contentHTML: contentDiv.html(), bar: false, submenu: false};
						get_wp_data('POST', data);
					} else {
						contentDiv.prepend("<p class='finalized-notice error'>" + errMsg + "</p>");
						animateNotice(em);
					}
				} else if(t.is("div#create-good-type input")){
					back = 1;
					back1 = {bar: jQuery("div.search-bar").clone(), contentHTML: jQuery("#content").html(), submenu: false};
					addBackButton();
					gn = form.find("input[name=good_name]").val();
					jQuery("div.search-bar").remove();
					jQuery("body").removeClass("with-bar");
					hideStuff();
					jQuery("#content").html('<div id="save-good-type"><form action="'+hu+'/give/" method="post"><input type="hidden" name="good_name" value="'+gn+'" /><p class="label">Quantity Units</p><p><select id="good_units" name="good_units" onchange="if(this.value == \'other\'){jQuery(\'.show-for-other\').show();jQuery(\'input.show-for-other\').focus();}else jQuery(\'.show-for-other\').hide();"><option value="">'+gn+'</option><option value="oz">oz(s)</option><option value="lb">lb(s)</option><option value="bag">bag(s)</option><option value="box">box(es)</option><option value="can">can(s)</option><option value="jar">jar(s)</option><option value="other">other</option></select></p><p><span class="explanation show-for-other" style="display:none;">How do you count this good?</span><input type="text" class="show-for-other" style="display:none;" id="good_units_other" name="good_units_other" value="'+gu+'" /></p><input type="hidden" name="action" value="save_new_good_type" /><input type="submit" value="Continue" /></form></div>');
					showStuff();
				} else if(t.is("div#save-good-type input")){
					data = {
						action: "fc_load_give", subaction: "create_new_good_type",
						good_name: form.find("input[name=good_name]").val(),
						good_units: form.find("input[name=good_units]").val(),
					};
					get_wp_data('POST', data);
				} else if(t.is("div#what-to-do input")){
					gn = jQuery("p#box-quantity").text();
					gq = gn.replace(/.*: /,"");
					gi = gq;
					xtraNames = new Array();
					xtraValues = new Array();
					if(gq.replace(/[0-9 ]*/,"") != "")
						gi += " of";
					gi += " " + gn.replace(/: [^:]*$/,"");
					data = {
						action: "fc_load_give", subaction: "finish_sharing",
						good_info: gi,
						box_post: form.find("input[name=box_post]").val()
					};
					extras.each(function(){
						var xtraname = jQuery(this).find("name").text(), v = "no";
						xtraNames.push(xtraname);
						if(form.find("input[name="+xtraname+"]:checked").val() == "yes")
							v = "yes";
						xtraValues.push(v);
					});
					data.extra_names = xtraNames.join(";");
					data.extra_values = xtraValues.join(";");
					get_wp_data('POST', data);
				} else if(t.is("div.foraged-box input")){
					data = {
						action: "fc_load_profile", subaction: "edit_foraged",
						box_post: form.find("input[name=box_post]").val()
					};
				   get_wp_data('GET', data);
				} else if(t.is("div.profile-shared-box input")){
					data = {
						action: "fc_load_profile", subaction: "edit_shared",
						box_post: form.find("input[name=box_post]").val()
					};
				   get_wp_data('GET', data);
				} else if(t.is("#post_comment")){
					data = {
						action: "fc_load_profile", subaction: "add_comment",
						comment_content: form.find("textarea[name=comment_content]").val(),
						box_post: form.find("input[name=box_post]").val()
					};
					get_wp_data('GET', data);
				} else if(t.is("#remove_shared_box")){
					data = {
						action: "fc_load_profile", subaction: "remove_shared",
						box_post: form.find("input[name=box_post]").val()
					};
					get_wp_data('GET', data);
				} else if(form.is("#cancel_profile_edit")){
					if(back == 1)
						jQuery("div#back-button input[type=submit]").click();
					else
					get_wp_data('GET', {action: "fc_load_profile", subaction: "get_info"});
				} else if(t.is("#edit_profile")){
					showProfile(e, "edit_info");
				} else if(t.is("#save_profile_edits")){
					data = {
						action: "fc_load_profile", subaction: "save_edits",
						user_login_cannot_change: form.find("input[name=user_login_cannot_change]").val(),
						first_name: form.find("input[name=first_name]").val(),
						last_name: form.find("input[name=last_name]").val(),
						email: form.find("input[name=email]").val(),
						address: form.find("input[name=address]").val(),
						search_radius: form.find("input[name=search_radius]").val(),
						pass1: form.find("input[name=pass1]").val(),
						pass2: form.find("input[name=pass2]").val(),
						user_id: form.find("input[name=user_id]").val(),
						description: form.find("textarea[name=description]").val(),
						affiliation: form.find("input[name=affiliation]").val(),
						from: "profile",
						// action: "update"
					};
					get_wp_data('POST', data);
				} else if(!t.is("div.foraged-box input")) {
					form.submit();
				}
				return false;
			} else {
				return true;
			}
		});

		jQuery("body").delegate("ul.submenu li a", "click touchend", function(e){
			if(!claimTapLock(this)){
				e.preventDefault();
				return false;
			}
			var t = jQuery(this), wc = t.parent().is(".current");
			if(t.is("ul.submenu li a")){
				jQuery("ul.submenu li").removeClass("current");
				t.parent().addClass('current');
			}
			if(t.is("li#sort-by-wishlist a")){
				e.preventDefault();
				data = {action: "fc_load_find", subaction: "get_list", good_name: jQuery("input[name=good_name]").val(), sort_by: "wishlist"};
				get_wp_data_with_loc('GET', data);
				return false;
			} else if(t.is("li#sort-by-recent a")){
				e.preventDefault();
				data = {action: "fc_load_find", subaction: "get_list", sort_by: "recent", good_name: jQuery("input[name=good_name]").val()};
				get_wp_data_with_loc('GET', data);
				return false;
			} else if(t.is("li#sort-by-map a")){
				e.preventDefault();
				data = {action: "fc_load_find", subaction: "get_list", sort_by: "nearby", good_name: jQuery("input[name=good_name]").val()};
				if(wc){
					jQuery("ul.submenu li#sort-by-map").attr("id","sort-by-nearby");
					t.attr("href",hu+"/find/nearby/");
					data.sort_by = "map";
				}
				get_wp_data_with_loc('GET', data);
				return false;
			} else if(t.is("li#sort-by-nearby a")){
				e.preventDefault();
				data = {action: "fc_load_find", subaction: "get_list", sort_by: "map", good_name: jQuery("input[name=good_name]").val()};
				if(wc){
					jQuery("ul.submenu li#sort-by-nearby").attr("id","sort-by-map");
					t.attr("href",hu+"/find/map/");
					data.sort_by = "nearby";
				}
				get_wp_data_with_loc('GET', data);
				return false;
			}
		});

		jQuery("body").delegate("div#good-type-search.find input[name=good_name]", "keyup input", function(e){
			e.preventDefault();
			clearTimeout(keyTO);
			keyTO = setTimeout(function(){
				var gn = jQuery("input[name=good_name]").val();
				lastgn = gn;
				data = {action: "fc_load_find", subaction: "get_list", good_name: gn};
				if(jQuery("ul.submenu li#sort-by-recent").is(".current"))
					data.sort_by = 'recent';
				else if(jQuery("ul.submenu li#sort-by-nearby").is(".current"))
					data.sort_by = 'map';
				else if(jQuery("ul.submenu li#sort-by-map").is(".current"))
					data.sort_by = 'nearby';
				else if(jQuery("ul.submenu li#sort-by-wishlist").is(".current"))
					data.sort_by = 'wishlist';
				else
					data.sort_by = defaultSort;
				get_wp_data_with_loc('GET', data);
			},5);
			return false;
		});

		jQuery("body").delegate("div#good-type-search.find input[value=Search]", clte, function(e){
			e.preventDefault();
			// alert("about to search find");
			if(!claimTapLock(this)){
				return false;
			}
			clearTimeout(keyTO);
			keyTO = setTimeout(function(){
				var gn = jQuery("input[name=good_name]").val();
				lastgn = gn;
				data = {action: "fc_load_find", subaction: "get_list", good_name: gn};
				if(jQuery("ul.submenu li#sort-by-recent").is(".current"))
					data.sort_by = 'recent';
				else if(jQuery("ul.submenu li#sort-by-nearby").is(".current"))
					data.sort_by = 'map';
				else if(jQuery("ul.submenu li#sort-by-map").is(".current"))
					data.sort_by = 'nearby';
				else if(jQuery("ul.submenu li#sort-by-wishlist").is(".current"))
					data.sort_by = 'wishlist';
				else
					data.sort_by = defaultSort;
				get_wp_data_with_loc('GET', data);
			},5);
			return false;
		});

		jQuery("body").delegate("div#good-type-search.give input[name=good_name]", "keyup input", function(e){
			e.preventDefault();
			var gn = jQuery(this).val();
			lastgn = gn;
			data = {action: "fc_load_give", subaction: "get_list", good_name: gn};
			get_wp_data('GET', data);
			return false;
		});

		jQuery("body").delegate("div#good-type-search.give input[value=Search]", clte, function(e){
			e.preventDefault();
			// alert("about to search give");
			if(!claimTapLock(this)){
				return false;
			}
			var gn = jQuery("input[name=good_name]").val();
			lastgn = gn;
			data = {action: "fc_load_give", subaction: "get_list", good_name: gn};
			get_wp_data('GET', data);
			return false;
		});

		showGive = function(e){
			e.preventDefault();
			// alert("about to show give");
			if(!claimTapLock(this)){
				return false;
			}
			jQuery("#container").attr("class","give");
			if(!jQuery(this).is(".current") || jQuery("div#back-button").length > 0) {
				data = {action: "fc_load_give", subaction: "get_list", clean_slate: "true"};
				get_wp_data('GET', data);
			}
			return false;
		};

		showFind = function(e, sortby){
			e.preventDefault();
			// console.log(e);
			var claimlock = claimTapLock(this);
			// alert("about to show find "+sortby+"   "+claimlock);
			if(!claimlock){
				return false;
			}
			jQuery("#container").attr("class","find");
			if(!jQuery(this).is(".current") || !jQuery("body").is(".with-submenu-and-bar")) {
				data = {action: "fc_load_find", subaction: "get_list", clean_slate: "true"};
				if(sortby == undefined)
					data.sort_by = "wishlist";
				else
					data.sort_by = sortby;
				get_wp_data_with_loc('GET', data);
			}
			return false;
		};

		showBasket = function(e){
			e.preventDefault();
			// alert("about to show basket");
			if(!claimTapLock(this)){
				return false;
			}
			jQuery("#container").attr("class","basket");
			if(!jQuery(this).is(".current") || jQuery("div#back-button").length > 0) {
				data = {action: "fc_load_basket", subaction: "get_list"};
				get_wp_data('GET', data);
			}
			return false;
		};

		showProfile = function(e, action, from){
			e.preventDefault();
			// alert("about to show profile "+action+" "+from);
			if(!claimTapLock(this)){
				return false;
			}
			jQuery("#container").attr("class","profile");
			if(!jQuery(this).is(".current") || jQuery("div#back-button").length > 0) {
				data = {action: "fc_load_profile"};
				if(action == undefined)	
					data.subaction = "get_info";
				else
					data.subaction = action;
				if(from != undefined)
					data.from = from;
				get_wp_data('GET', data);
			}
			return false;
		};

		jQuery("#nav-menu a").not("a[title=profile]").bind(clte, function(e){
			// alert("about to clear back button?");
			jQuery(".top-right-button").add(".top-left-button").remove();
		});

		jQuery("#nav-menu a[title=give]").bind(clte, showGive);
		jQuery("body").delegate("#give_button", "click touchend", showGive);

		jQuery("#nav-menu a[title=find]").bind(clte, showFind);
		jQuery("body").delegate("#wishlist_button", "click touchend", showFind);
		jQuery("body").delegate("#recent_button", "click touchend", function(e){
			showFind(e, "recent");
		});
		jQuery("body").delegate("#nearby_button", "click touchend", function(e){
			showFind(e, "nearby");
		});
		jQuery("body").delegate("#map_button", "click touchend", function(e){
			// e.preventDefault();return false;
			showFind(e, "map");
		});

		jQuery("#nav-menu a[title=basket]").bind(clte, showBasket);
		jQuery("body").delegate("#checkout_button", "click touchend", showBasket);

		jQuery("#nav-menu a[title=profile]").bind(clte, showProfile);
		jQuery("body").delegate("#settings_button", "click touchend", function(e){
			showProfile(e, "edit_info", "home");
		});

		jQuery("#nav-menu a[title=home]").add("div#home-button a").add("div#info-close-button a").bind(clte, function(e){
			e.preventDefault();
			// alert("about to show home");
			if(!claimTapLock(this)){
				return false;
			}
			jQuery(".top-right-button").add(".top-left-button").remove();
			jQuery("#container").attr("class","homemenu");
			jQuery("#nav-menu a").removeClass("current");
			jQuery(this).addClass("current");
			jQuery("div.search-bar").add("ul.submenu").remove();
			jQuery("body").removeClass("with-bar with-submenu with-submenu-and-bar");
			hideStuff();
			jQuery("#content").html('<div id="home_menu"><a id="nearby_button" href="'+hu+'/find/nearby/"><span>Nearby</span></a><a id="map_button" class="right" href="'+hu+'/find/map/"><span>Nearby/Map</span></a><a id="give_button" href="'+hu+'/give/"><span>Give</span></a><a id="checkout_button" class="right" href="'+hu+'/basket/"><span>Check Out</span></a><a id="wishlist_button" href="'+hu+'/find/wishlist/"><span>Wishlist</span></a><a id="settings_button" class="right" href="'+hu+'/profile/edit/"><span>Settings</span></a><div style="clear:both;"></div></div>');
			jQuery("div#top-buttons").addClass("homemenu").removeClass("page");
			showStuff();
			return false;
		});

		jQuery("body").delegate("#back-button input","touchstart",function(){
			top_btn = true;
		});
		jQuery("body").delegate("#back-button input","touchmove",function(){
			top_btn = false;
		})
		jQuery("body").delegate("#back-button input",clte,function(e){
			e.preventDefault();
			// alert("about to go back");
			if(!claimTapLock(this)){
				return false;
			}
			if(top_btn || !hasTouch){
				hideStuff();
				if(back == 1){
					jQuery("#content").html(back1.contentHTML);
					if(back1.bar) {
						sw.before(back1.bar);
						jQuery("body").addClass("with-bar");
					}
					if(back1.submenu){
						sw.before(back1.submenu);
						jQuery("body").addClass("with-submenu");
						if(back1.bar)
							jQuery("body").addClass("with-submenu-and-bar");
					}
					if(back1.rightbtn)
						sw.before(back1.rightbtn);
					if(back1.leftbtn)
						sw.before(back1.leftbtn);
					jQuery("#back-button").remove();
					var to = 250;
					if(iOS) to = 0;
					setTimeout('jQuery("div#container").removeClass("with-back-button");', to);
				} else if(back == 2){
					jQuery("#content").html(back2.contentHTML);
					if(back2.bar) {
						sw.before(back2.bar);
						jQuery("body").addClass("with-bar");
					}
					if(back2.submenu){
						sw.before(back2.submenu);
						jQuery("body").addClass("with-submenu");
						if(back2.bar)
							jQuery("body").addClass("with-submenu-and-bar");
					}
					if(back2.rightbtn)
						sw.before(back2.rightbtn);
					if(back2.leftbtn)
						sw.before(back2.leftbtn);
				}
				back--;
				backHelper();
				// console.log("executed backHelper");
				showStuff();
			}
			top_btn = false;
			return false;
		});

		// disable clicking the logo for now, since it's too greedy
		jQuery("#branding a").bind("click touchend",function(e){
			// if(top_btn){
			e.preventDefault();
			return false;
			// }
		});

		setTimeout(delayedInit,100);
	},
	delayedInit = function(){
		if (navigator.userAgent.match(/Android/i)) {
			window.scrollTo(0,0); // reset in case prev not scrolled  
			var nPageH = jQuery(document).height(), nViewH = window.outerHeight;
			if (nViewH > nPageH) {
				nViewH -= 250;
				// jQuery('BODY').css('height',nViewH + 'px');
			}
			// window.scrollTo(0,1);
			jQuery("body").addClass("android");
		}
		window.scrollTo(0,1);
		if(iOS) {
			myScroll = new iScroll('scroll-wrapper', {
				// useTransform: false,
				onBeforeScrollStart: function (e) {
					if(iOS)
						e.preventDefault();
					else {
						var target = e.target, tartag;
						while (target.nodeType != 1) target = target.parentNode;
						tartag = target.tagName.toUpperCase();
						if (tartag != 'SELECT' && tartag != 'INPUT' && tartag != 'TEXTAREA' && tartag != 'LABEL'){
							e.preventDefault();
						}
					}
				}
			});
		} else {
			jQuery("body").addClass("not-iOS").css("height",jQuery(window).height()+"px");
			fixScrollWrapper();
			fixFooter();
		}
	};//,counter=0;

//onload
jQuery(function(){
	var sw, data;
	document.documentElement.className = document.documentElement.className.replace(/\bno-js\b/, '');
	document.addEventListener('click touchend', function (e) { e.stopPropagation(); }, false);
	// alert(1/window.devicePixelRatio);
	// document.addEventListener('touchmove', function (e) { e.preventDefault(); }, false);
	if(!(navigator.userAgent.match(/iPhone/i)) &&
		!(navigator.userAgent.match(/iPod/i)) &&
		!(navigator.userAgent.match(/iPad/i)))
	{ iOS = false; }
	//{ iOS = true; }
	initialize();
	if(jQuery("li#sort-by-nearby").is(".current")){
		data = {action: "fc_load_find", subaction: "get_list", clean_slate: "true", sort_by: "map"};
		get_wp_data_with_loc('GET', data);
	}
	if(!iOS){
		sw = document.getElementById("scroll-wrapper"),
		// Detect whether device supports orientationchange event, otherwise fall back to
		// the resize event.
			supportsOrientationChange = "onorientationchange" in window,
			orientationEvent = supportsOrientationChange ? "orientationchange" : "resize";

		jQuery(window).bind(orientationEvent, function(){setTimeout("fixFooter();",100);});
		
		sw.ontouchstart = sw.ontouchmove = function(){
			if(jQuery("#scroll-wrapper").height() > jQuery("body").height())
				jQuery("#footer").css({"top":"auto","bottom":"0"});
		};
		sw.ontouchend = function(){
			// jQuery("#footer").css("display","block");
			fixFooter();
		}
		window.onscroll = fixFooter;
		// alert(pixrat+" "+navigator.userAgent);
	}
})