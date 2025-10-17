(function($) {
    'use strict';

    var CEM_Maps = {
        map: null,
        marker: null,
        searchBox: null,
        geocoder: null,
        
        init: function() {
            if ($('.cem-map').length === 0) return;
            
            this.initMap();
            this.initAdminFeatures();
            this.bindEvents();
        },

        initMap: function() {
            var mapElement = $('.cem-map')[0];
            var location = {
                lat: parseFloat($('#cem-lat').val()) || 0,
                lng: parseFloat($('#cem-lng').val()) || 0
            };

            var mapOptions = {
                zoom: 15,
                center: location,
                mapTypeControl: true,
                streetViewControl: true,
                fullscreenControl: true
            };

            this.map = new google.maps.Map(mapElement, mapOptions);
            this.geocoder = new google.maps.Geocoder();

            if (location.lat !== 0 && location.lng !== 0) {
                this.addMarker(location);
            }
        },

        initAdminFeatures: function() {
            if (!$('#cem-location-search').length) return;

            this.searchBox = new google.maps.places.SearchBox(
                document.getElementById('cem-location-search')
            );

            this.map.addListener('bounds_changed', function() {
                CEM_Maps.searchBox.setBounds(CEM_Maps.map.getBounds());
            });

            this.searchBox.addListener('places_changed', function() {
                var places = CEM_Maps.searchBox.getPlaces();
                if (places.length === 0) return;

                var place = places[0];
                if (!place.geometry) return;

                CEM_Maps.updateLocation(place);
            });
        },

        bindEvents: function() {
            $('#cem-location-search').on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    var address = $(this).val();
                    CEM_Maps.geocodeAddress(address);
                }
            });

            $('.get-directions').on('click', function(e) {
                e.preventDefault();
                var lat = $('#cem-lat').val();
                var lng = $('#cem-lng').val();
                window.open('https://www.google.com/maps/dir//' + lat + ',' + lng);
            });
        },

        addMarker: function(location) {
            if (this.marker) {
                this.marker.setMap(null);
            }

            this.marker = new google.maps.Marker({
                position: location,
                map: this.map,
                draggable: !!$('#cem-location-search').length
            });

            if (this.marker.getDraggable()) {
                this.marker.addListener('dragend', function() {
                    var position = CEM_Maps.marker.getPosition();
                    CEM_Maps.reverseGeocode(position);
                });
            }

            this.map.setCenter(location);
        },

        updateLocation: function(place) {
            var location = place.geometry.location;
            
            $('#cem-lat').val(location.lat());
            $('#cem-lng').val(location.lng());
            $('#cem-formatted-address').val(place.formatted_address);
            $('#cem-location-search').val(place.formatted_address);
            // Sync formatted address into the main Location field in admin
            var $locInput = $('#location');
            if ($locInput.length) {
                $locInput.val(place.formatted_address);
            }

            this.addMarker(location);
        },

        geocodeAddress: function(address) {
            this.geocoder.geocode({ address: address }, function(results, status) {
                if (status === 'OK') {
                    var place = {
                        geometry: {
                            location: results[0].geometry.location
                        },
                        formatted_address: results[0].formatted_address
                    };
                    CEM_Maps.updateLocation(place);
                }
            });
        },

        reverseGeocode: function(latlng) {
            this.geocoder.geocode({ location: latlng }, function(results, status) {
                if (status === 'OK') {
                    var place = {
                        geometry: {
                            location: latlng
                        },
                        formatted_address: results[0].formatted_address
                    };
                    CEM_Maps.updateLocation(place);
                }
            });
        }
    };

    $(document).ready(function() {
        CEM_Maps.init();
    });

})(jQuery);