/* global wp, jQuery, Backbone, JSON, _widgetFavorites_exports */
/* exported widgetFavorites */
var widgetFavorites = (function ( $ ) {
	var self;

	self = {
		nonce: '',
		templates: {
			beforeStar: null,
			star: null,
			ui: null
		},
		l10n: {
			tooltip_show_favorites: '',
			tooltip_hide_favorites: '',
			create_new_option_label: '',
			untitled: '',
			tooltip_widget_instance_option: '' // 1: name, 2: created_date, 3: modified_date
		},
		ajaxAction: '',
		WidgetInstance: null,
		widget_id_bases: [],
		collections: {}
		// @todo Backbone collection for each type
		// @todo list of instances
	};
	$.extend( self, _widgetFavorites_exports );

	/**
	 * @type {Backbone.Model}
	 */
	self.WidgetInstance = Backbone.Model.extend({
		idAttribute: 'post_id',
		defaults: {
			name: '',
			src_widget_id: '',
			sanitized_widget_setting: null,
			author_id: 0,
			author_display_name: 0,
			datetime_created: '',
			datetime_modified: ''
		},

		parse: function ( response ) {
			if ( response.datetime_created ) {
				response.datetime_created = new Date( response.datetime_created );
			}
			if ( response.datetime_modified ) {
				response.datetime_modified = new Date( response.datetime_modified );
			}
			return response;
		},

		/**
		 *
		 * @returns {wp.customize.Value}
		 */
		getCustomizeSetting: function () {
			var settingId;
			if ( ! this.get( 'src_widget_id' ) ) {
				return null;
			}
			settingId = self.widgetIdToSettingId( this.get( 'src_widget_id' ) );
			if ( ! settingId ) {
				return null;
			}
			if ( ! wp.customize.has( settingId ) ) {
				return null;
			}
			return wp.customize( settingId );
		},

		/**
		 * Basic
		 *
		 * @param attributes
		 * @returns {*}
		 */
		validate: function ( attributes ) {
			if ( ! attributes.src_widget_id ) {
				return new Error( 'missing src_widget_id' );
			}
			if ( ! attributes.sanitized_widget_setting ) {
				return new Error( 'missing sanitized_widget_setting' );
			}
			return null;
		},

		toJSON: function () {
			var exported = Backbone.Model.prototype.toJSON.apply( this, arguments );
			delete exported.author_id; // @todo maybe sometime allow this
			delete exported.author_display_name;
			delete exported.datetime_created;
			delete exported.datetime_modified;
			return exported;
		},

		sync: function ( method, collection, options ) {
			return self.sync( method, collection, options );
		}
	});

	/**
	 * Backbone Collection to contain the Widget Favorites of a given widget type.
	 */
	self.WidgetTypeCollection = Backbone.Collection.extend({
		model: self.WidgetInstance,

		initialize: function ( models, options ) {
			var collection = this;
			options = options || {};
			if ( ! options.id_base ) {
				throw new Error( 'Must supply id_base option' );
			}
			collection.id_base = options.id_base;
			collection.syncedTime = 0;
			collection.on( 'sync', function () {
				collection.syncedTime = new Date().valueOf();
			});
			return Backbone.Collection.prototype.initialize.call( collection, models, options );
		},

		comparator: function( model ) {
			return -model.get( 'datetime_created' ).getTime();
		},

		sync: function ( method, model, options ) {
			return self.sync( method, model, options );
		}
	});

	/**
	 * Model to manage the Star icon.
	 */
	self.StarView = Backbone.View.extend({
		initialize: function ( options ) {
			this.control = options.control;
		},

		template: wp.template( 'widget-favorites-star' ),

		events: {
			'click a': 'toggle'
		},

		render: function () {
			var contents = this.template({
				l10n: self.l10n
			});
			this.$el.empty().append( contents );
		},

		toggle: function () {
			this.control.toggle();
			if ( this.control.expanded ) {
				this.$( 'a' ).prop( 'title', self.l10n.tooltip_hide_favorites );
			} else{
				this.$( 'a' ).prop( 'title', self.l10n.tooltip_show_favorites );
			}
		}
	});

	/**
	 * Model to manage the Favorites icon.
	 */
	self.FavoritesView = Backbone.View.extend({
		template: wp.template( 'widget-favorites-ui' ),

		events: {
			'click .widget-favorites-save': 'save',
			'click .widget-favorites-load': 'load',
			'change .widget-favorites-select': 'changeSelect'
		},

		initialize: function ( options ) {
			var view = this;
			view.control = options.control; // @todo rename as controller
			this.disabledInterfaceLevel = 0;

			view.collection.on( 'change add remove', function () {
				view.populateSelect();
			});
		},

		/**
		 * Render the template onto the view's element
		 */
		render: function () {
			var view = this,
				req,
				contents;

			if ( ! view.$el.is( ':empty' ) ) {
				// Already populated
				return;
			}

			contents = this.template({
				l10n: self.l10n
			});
			this.$el.empty().append( contents );

			view.populateSelect();
			if ( ! view.collection.syncedTime ) { // @todo re-fetch if stale?
				view.disableInterface();
				req = view.collection.fetch();
				req.fail( function ( jqxhr ) {
					view.setError( view.getErrorMessage( jqxhr ) );
				} );
				req.done( function () {
					view.populateSelect();
					view.setError( null );
				} );
				req.always(function () {
					view.enableInterface();
				});
			}

		},

		/**
		 * Populate the instance select with the collection's models
		 */
		populateSelect: function () {
			var view = this,
				select = view.$el.find( 'select' ),
				value = select.val();

			select.find( 'option' ).remove();

			if ( ! view.collection.length ) {
				select.closest( '.widget-favorites-control-row' ).hide();
			} else {
				select.closest( '.widget-favorites-control-row' ).show();
				select.append( new Option( self.l10n.create_new_option_label, '' ) );
				view.collection.each( function ( model ) {
					var option, title;
					option = new Option( model.get( 'name' ) || self.l10n.untitled, model.get( 'post_id' ) );
					title = self.l10n.tooltip_widget_instance_option;
					title = title.replace( '%1$s', model.get( 'author_display_name' ) );
					title = title.replace( '%2$s', model.get( 'datetime_created' ) );
					title = title.replace( '%3$s', model.get( 'datetime_modified' ) );
					option.title = title;
					select.append( option );
				} );

				// Restore selected value
				if ( value ) {
					select.val( value );
				}
			}
			select.trigger( 'change' );
		},

		/**
		 * Display an error message or clear it from the display.
		 *
		 * @param {string|null} [message]
		 */
		setError: function ( message ) {
			var view = this,
				errorContainer = view.$el.find( '.widget-favorites-error' ),
				errorMessageEl = errorContainer.find( '.widget-favorites-error-message' );
			if ( ! message ) {
				errorContainer.slideUp( function () {
					errorMessageEl.text( '' );
				} );
			} else {
				errorContainer.stop();
				errorMessageEl.text( message );
				errorContainer.slideDown();
			}
		},

		/**
		 * Get the error message from the WP Ajax jqxhr.
		 *
		 * @param jqxhr
		 * @return string
		 */
		getErrorMessage: function ( jqxhr ) {
			var errorMessage;
			if ( jqxhr.responseJSON && false === jqxhr.responseJSON.success && typeof jqxhr.responseJSON.data === 'string' ) {
				errorMessage = jqxhr.responseJSON.data;
			} else {
				errorMessage = jqxhr.statusText;
			}
			return errorMessage;
		},

		/**
		 * Make the spinner visible, and increment a count so multiple requests
		 * can be concurrent.
		 */
		disableInterface: function () {
			this.disabledInterfaceLevel += 1;
			if ( 1 === this.disabledInterfaceLevel ) {
				this.$( '.spinner' ).addClass( 'visible' );
				this.$( ':input' ).prop( 'disabled', true );
			}
		},

		/**
		 * Hide the spinner when there are no pending spinners open.
		 */
		enableInterface: function () {
			this.disabledInterfaceLevel -= 1;
			if ( this.disabledInterfaceLevel < 0 ) {
				this.disabledInterfaceLevel = 0;
			}
			if ( 0 === this.disabledInterfaceLevel ) {
				this.$( '.spinner' ).removeClass( 'visible' );
				this.$( ':input' ).prop( 'disabled', false );
			}
		},

		/**
		 * Handle a change (either of selection of contents) to the instance select
		 */
		changeSelect: function () {
			var select = this.$( 'select' );
			this.$( '.widget-favorites-load' ).toggle( !! select.val() );
		},

		/**
		 * Given the currently-selected instance, if any, update the widget's
		 * setting with the associated value.
		 */
		load: function () {
			var select, model, post_id, view = this;
			select = this.$( 'select' );
			post_id = select.val();
			if ( ! post_id ) {
				return;
			}
			model = view.collection.get( post_id );
			if ( ! model ) {
				return;
			}
			view.disableInterface();
			view.control.customizeControl.updateWidget({
				instance: model.get( 'sanitized_widget_setting' ),
				complete: function () {
					view.enableInterface();
				}
			});
		},

		/**
		 * Save a new instance if none is currently selected. Otherwise, update
		 * the currently-selected instance.
		 */
		save: function () {
			var isNew, select, model, post_id, attrs, xhr, validationError, view = this;

			select = view.$( '.widget-favorites-select' );
			post_id = +select.val();
			isNew = ! post_id;

			attrs = {
				name: view.$( '.widget-favorites-save-name' ).val(),
				src_widget_id: view.control.customizeControl.params.widget_id,
				sanitized_widget_setting: view.control.customizeControl.setting()
			};
			view.disableInterface();
			if ( isNew ) {
				model = new self.WidgetInstance();
			} else {
				model = view.collection.get( +post_id ).set();
			}

			view.setError( null );

			validationError = model.validate( attrs );
			if ( validationError ) {
				view.setError( validationError );
				return;
			}

			xhr = model.save( attrs, { wait: true } );
			xhr.done(function () {
				if ( isNew ) {
					view.collection.add( model );
					select.val( model.get( 'post_id' ) ).trigger( 'change' );
				}
			});
			xhr.fail(function ( jqxhr ) {
				view.setError( view.getErrorMessage( jqxhr ) );
			});
			xhr.always(function () {
				view.enableInterface();
			});
		}
	});

	/**
	 * Controller for a widget form control's specific widget favorite functionality.
	 *
	 * @param {wp.customize.Control} customizeControl
	 * @constructor
	 */
	self.Control = function ( customizeControl ) {
		this.customizeControl = customizeControl;
		this.expanded = false;

		this.starContainer = $( '<span>' );
		customizeControl.container.find( '.widget-control-actions > .alignleft' ).append( this.starContainer );

		this.starView = new self.StarView({
			control: this,
			el: this.starContainer
		});
		this.starView.render();

		this.favoritesContainer = $( '<div>' );

		this.favoritesView = new self.FavoritesView({
			control: this,
			collection: self.initCollection( this.customizeControl.params.widget_id_base ),
			el: this.favoritesContainer
		});
	};

	/**
	 * Toggle the visibility of the favoriting UI.
	 *
	 * @param {Boolean} [expanded]
	 * @returns {Boolean}
	 */
	self.Control.prototype.toggle = function ( expanded ) {
		var control = this;
		if ( typeof expanded === 'undefined' ) {
			expanded = ! control.expanded;
		}
		if ( expanded ) {
			control.customizeControl.container.find( '.widget-control-actions' ).append( control.favoritesContainer );
			control.favoritesContainer.hide();
			control.favoritesView.render();
			control.favoritesContainer.slideDown();
		} else {
			control.favoritesContainer.slideUp();
		}
		control.expanded = expanded;
		return expanded;
	};

	/**
	 * Boot the functionality
	 */
	self.init = function () {
		if ( this.initialized ) {
			return;
		}
		if ( typeof wp === 'undefined' || typeof wp.customize === 'undefined' || typeof wp.customize.Widgets === 'undefined' ) {
			throw new Error( 'Favorite Widgets is only supported in the Customizer' );
		}

		this.initialized = true;

		$( document ).on( 'widget-added', this.onWidgetAdded );

		this.createCollections();
	};

	/**
	 * Populate the collections and re-populate whenever the availableWidgets is added to
	 */
	self.createCollections = function () {
		var self = this;

		self.collections = {};
		self.populateCollections();
		wp.customize.Widgets.availableWidgets.on( 'add', function () {
			self.populateCollections();
		} );
	};

	/**
	 * Ensure that the collections object contains a WidgetTypeCollection for each availableWidgets
	 */
	self.populateCollections = function () {
		var self = this;
		wp.customize.Widgets.availableWidgets.each( function ( availableWidget ) {
			self.initCollection( availableWidget.get( 'id_base' ) );
		});
		// @todo low priority: delete a collection if it no longer exists among availableWidgets
	};

	/**
	 *
	 * @param id_base
	 * @returns {self.WidgetTypeCollection}
	 */
	self.initCollection = function ( id_base ) {
		if ( ! self.collections[ id_base ] ) {
			self.collections[ id_base ] = new self.WidgetTypeCollection( [], { id_base: id_base } );
		}
		return self.collections[ id_base ];
	};

	/**
	 * Extend each widget form control when added
	 *
	 * @todo We might as well hook into wp.customize.control.bind( 'add' );
	 *
	 * @param {jQuery.Event} e
	 * @param {jQuery} container
	 */
	self.onWidgetAdded = function ( e, container ) {
		var widgetId, customizeId;
		widgetId = container.find( 'input.widget-id' ).val();
		if ( widgetId ) {
			customizeId = self.widgetIdToSettingId( widgetId );
			wp.customize.control( customizeId, function ( customizeControl ) {
				customizeControl.widgetFavorites = new self.Control( customizeControl );
			} );
		}
	};

	/**
	 *
	 * @param {string} method
	 * @param {self.WidgetInstance} model
	 * @param {*} options
	 * @return {$.promise}
	 */
	self.sync = function ( method, model, options ) {
		options = options || {};
		var req,
			self = this,
			data = {};
		data.nonce = self.nonce;
		data.action = self.ajaxAction;
		data.method = method;

		/*
		 * Sending wp_customize=on is needed the Customizer will be loaded early
		 * enough so that the non-pluggable functions defined in
		 * class-wp-customize-manager.php can be defined before the theme or plugins
		 * load and try to define it themselves.
		 *
		 * See https://github.com/xwp/wp-widget-favorites/issues/5
		 */
		data.wp_customize = 'on';

		if ( 'create' === method || 'update' === method ) {
			$.extend( data, model.toJSON() );
			data.sanitized_widget_setting = JSON.stringify( data.sanitized_widget_setting );
		} else if ( 'read' === method ) {
			if ( model instanceof Backbone.Collection && model.id_base ) {
				data.id_base = model.id_base;
			} else if ( model instanceof self.WidgetInstance ) {
				data.post_id = model.get( 'post_id' );
			} else {
				throw new Error( 'unrecognized model' );
			}
		} else if ( 'delete' === method ) {
			data.post_id = model.get( 'post_id' );
		} else {
			throw new Error( 'Unrecognized method: ' + method );
		}

		options.data = data;
		req = wp.ajax.send( 'widget_favorites_sync', options );
		options.xhr = req;
		model.trigger( 'request', model, req, options );
		return req;
	};

	/**
	 *
	 * @param {self.WidgetInstance} model
	 */
	self.update = function( model ) {
		var error;
		error = model.validate();
		if ( error ) {
			if ( typeof error === 'string' ) {
				error = new Error( error );
			}
			throw error;
		}

	};

	/**
	 *
	 * @param {self.WidgetInstance} model
	 * @returns {*}
	 */
	self.create = function ( model ) {
		return this.update( model );
	};


	/**
	 * @todo This is copied from customize-widgets.js, but it should be public there.
	 *
	 * @param {String} widgetId
	 * @returns {Object}
	 */
	self.parseWidgetId = function ( widgetId ) {
		var matches, parsed = {
			number: null,
			id_base: null
		};

		matches = widgetId.match( /^(.+)-(\d+)$/ );
		if ( matches ) {
			parsed.id_base = matches[1];
			parsed.number = parseInt( matches[2], 10 );
		} else {
			// likely an old single widget
			parsed.id_base = widgetId;
		}

		return parsed;
	};

	/**
	 * @todo This is copied from customize-widgets.js, but it should be public there.
	 *
	 * @param {String} widgetId
	 * @returns {String} settingId
	 */
	self.widgetIdToSettingId = function ( widgetId ) {
		var parsed = self.parseWidgetId( widgetId ), settingId;

		settingId = 'widget_' + parsed.id_base;
		if ( parsed.number ) {
			settingId += '[' + parsed.number + ']';
		}

		return settingId;
	};

	return self;

}( jQuery ));
