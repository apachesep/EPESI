var Base_Notify = {
	token: 0,
	last_refresh: 0,
	interval: 0,
	disabled: 0,
	disabled_message: 'Notifications disabled!',
	
	init: function(refresh_interval, disabled_message) {
		this.set_interval(refresh_interval);
		this.disabled_message = disabled_message;
	},
	
	set_interval: function (t) {
		if (!this.is_active()) return;
		
		clearInterval(this.interval);
				
		this.interval = setInterval(function () {Base_Notify.refresh();}, t);
	},
	
	refresh: function (check_user) {
		if (!this.is_active()) return;
		
		check_user = check_user || 0;
		
		jq.getJSON('modules/Base/Notify/refresh.php?cid='+Epesi.client_id+'&last_refresh='+this.last_refresh+'&token='+this.token+'&check_user='+check_user, function(json){
			if (typeof json === 'undefined' || jq.isEmptyObject(json)) return;
			if (typeof json.disable !== 'undefined') {
				Base_Notify.disable();
				return;	
			}

			if (typeof json.last_refresh !== 'undefined') Base_Notify.last_refresh = json.last_refresh;			
			if (typeof json.token !== 'undefined') Base_Notify.token = json.token;
			
			if (!Base_Notify.token) return;
			
			if (typeof json.messages === 'undefined') return;
			
			jq.each(json.messages, function(i, m) {
				setTimeout(function(){
					if (typeof m.timeout !== 'undefined') notify.config({pageVisibility: false, autoClose: m.timeout});
					Base_Notify.notify(m.title, m.opts);			
				}, i*500);
			});
		});		
	},
	
	notify: function (title, opts, check) {
		if (!this.is_active(true)) return;
		
		if (notify.permissionLevel() === notify.PERMISSION_DEFAULT) {
			notify.requestPermission(function (permission) {
				if (permission === notify.PERMISSION_GRANTED) {
					var n = notify.createNotification(title, opts);
				}
			});
		}
		else if (notify.permissionLevel() === notify.PERMISSION_GRANTED) {
			var n = notify.createNotification(title, opts);
		}
	},
	
	is_active: function (alert) {
		if (this.disabled) return false;
		
		if (!this.is_supported(alert)) {
			this.disable();
			return false;
		}
		
		return true;
	},
	
	is_supported: function (alert) {
		supported = notify.isSupported && (notify.permissionLevel() !== notify.PERMISSION_DENIED);
		
		if (!supported && alert) alert(this.disabled_message);
		
		return supported && !this.disabled;
	},
	
	disable: function () {
		clearInterval(this.interval);
		this.interval = 0;
		this.disabled = 1;
	}
}
