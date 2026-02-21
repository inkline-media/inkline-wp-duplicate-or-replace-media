/**
 * Inkline Replace Media — form interactions.
 * Vanilla JS, no jQuery.
 */
(function () {
    'use strict';

    var opts      = window.inklineReplaceOptions || {};
    var maxSize   = parseInt( opts.maxFileSize, 10 ) || 0;
    var sourceType = ( opts.sourceType || '' ).trim();

    // DOM references.
    var fileInput      = document.getElementById( 'inkline-file-input' );
    var submitBtn      = document.getElementById( 'inkline-submit' );
    var previewBox     = document.getElementById( 'inkline-preview-new' );
    var uploadArea     = document.getElementById( 'inkline-upload-area' );
    var dropOverlay    = document.getElementById( 'inkline-drop-overlay' );
    var customDateWrap = document.getElementById( 'inkline-custom-date' );
    var newName        = document.getElementById( 'inkline-new-name' );
    var newDimensions  = document.getElementById( 'inkline-new-dimensions' );
    var newSize        = document.getElementById( 'inkline-new-size' );

    var errorFilesize  = document.querySelector( '.inkline-error-filesize' );
    var warningType    = document.querySelector( '.inkline-warning-filetype' );
    var sourceTypeSpan = document.querySelector( '.inkline-source-type' );
    var targetTypeSpan = document.querySelector( '.inkline-target-type' );

    // -----------------------------------------------------------------------
    // File selection handler
    // -----------------------------------------------------------------------

    if ( fileInput ) {
        fileInput.addEventListener( 'change', handleFileSelect );
    }

    function handleFileSelect() {
        hideMessages();

        var file = fileInput.files && fileInput.files[0];
        if ( ! file ) {
            clearPreview();
            toggleSubmit( false );
            return;
        }

        // Size check.
        if ( maxSize && file.size > maxSize ) {
            showEl( errorFilesize );
            fileInput.value = '';
            clearPreview();
            toggleSubmit( false );
            return;
        }

        // Type mismatch warning.
        var targetType = ( file.type || '' ).trim();
        if ( sourceType && targetType && targetType !== sourceType ) {
            if ( ! isFalsePositiveType( sourceType, targetType ) ) {
                if ( sourceTypeSpan ) sourceTypeSpan.textContent = sourceType;
                if ( targetTypeSpan ) targetTypeSpan.textContent = targetType;
                showEl( warningType );
            }
        }

        // Update preview.
        updatePreview( file );
        toggleSubmit( true );
    }

    // -----------------------------------------------------------------------
    // Preview
    // -----------------------------------------------------------------------

    function updatePreview( file ) {
        // Remove existing preview image.
        var oldImg = previewBox ? previewBox.querySelector( '.inkline-preview-img' ) : null;
        if ( oldImg ) oldImg.remove();

        // Hide upload area text.
        if ( uploadArea ) uploadArea.style.display = 'none';

        // Name + size.
        if ( newName ) newName.textContent = file.name;
        if ( newSize ) newSize.textContent = formatBytes( file.size );
        if ( newDimensions ) newDimensions.textContent = '';

        var isImage = file.type && file.type.indexOf( 'image' ) === 0;

        if ( isImage ) {
            var img = new Image();
            img.src = URL.createObjectURL( file );
            img.className = 'inkline-preview-img';
            img.addEventListener( 'load', function () {
                var w = img.naturalWidth || img.width;
                var h = img.naturalHeight || img.height;
                if ( newDimensions ) newDimensions.textContent = w + ' \u00d7 ' + h;
            } );
            if ( previewBox ) {
                previewBox.insertBefore( img, previewBox.querySelector( '.inkline-preview-meta' ) );
            }
        } else {
            // Non-image: show generic icon.
            var icon = document.createElement( 'span' );
            icon.className = 'dashicons dashicons-media-default inkline-preview-icon inkline-preview-img';
            if ( previewBox ) {
                previewBox.insertBefore( icon, previewBox.querySelector( '.inkline-preview-meta' ) );
            }
        }
    }

    function clearPreview() {
        var oldImg = previewBox ? previewBox.querySelector( '.inkline-preview-img' ) : null;
        if ( oldImg ) oldImg.remove();
        if ( uploadArea ) uploadArea.style.display = '';
        if ( newName ) newName.textContent = '';
        if ( newDimensions ) newDimensions.textContent = '';
        if ( newSize ) newSize.textContent = '';
    }

    // -----------------------------------------------------------------------
    // Drag and drop
    // -----------------------------------------------------------------------

    var dragCounter = 0;

    document.addEventListener( 'dragenter', function ( e ) {
        e.preventDefault();
        dragCounter++;
        if ( dropOverlay ) dropOverlay.style.display = 'flex';
    } );

    document.addEventListener( 'dragleave', function ( e ) {
        e.preventDefault();
        dragCounter--;
        if ( dragCounter <= 0 ) {
            dragCounter = 0;
            if ( dropOverlay ) dropOverlay.style.display = 'none';
        }
    } );

    document.addEventListener( 'dragover', function ( e ) {
        e.preventDefault();
    } );

    document.addEventListener( 'drop', function ( e ) {
        e.preventDefault();
        dragCounter = 0;
        if ( dropOverlay ) dropOverlay.style.display = 'none';
    } );

    if ( dropOverlay ) {
        dropOverlay.addEventListener( 'drop', function ( e ) {
            e.preventDefault();
            e.stopPropagation();
            dragCounter = 0;
            dropOverlay.style.display = 'none';

            if ( e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length && fileInput ) {
                fileInput.files = e.dataTransfer.files;
                fileInput.dispatchEvent( new Event( 'change', { bubbles: true } ) );
            }
        } );
    }

    // -----------------------------------------------------------------------
    // Custom date toggle
    // -----------------------------------------------------------------------

    var timestampRadios = document.querySelectorAll( 'input[name="timestamp_replace"]' );
    timestampRadios.forEach( function ( radio ) {
        radio.addEventListener( 'change', toggleCustomDate );
    } );
    toggleCustomDate();

    function toggleCustomDate() {
        var checked = document.querySelector( 'input[name="timestamp_replace"]:checked' );
        if ( ! customDateWrap ) return;
        customDateWrap.style.display = ( checked && checked.value === '3' ) ? 'block' : 'none';
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    function toggleSubmit( enabled ) {
        if ( submitBtn ) submitBtn.disabled = ! enabled;
    }

    function hideMessages() {
        hideEl( errorFilesize );
        hideEl( warningType );
    }

    function showEl( el ) {
        if ( el ) el.style.display = 'block';
    }

    function hideEl( el ) {
        if ( el ) el.style.display = 'none';
    }

    function formatBytes( bytes ) {
        if ( bytes === 0 ) return '0 B';
        var k = 1024;
        var sizes = [ 'B', 'KB', 'MB', 'GB' ];
        var i = Math.floor( Math.log( bytes ) / Math.log( k ) );
        return parseFloat( ( bytes / Math.pow( k, i ) ).toFixed( 2 ) ) + ' ' + sizes[ i ];
    }

    function isFalsePositiveType( a, b ) {
        // Windows reports zip variants differently.
        if ( a.indexOf( 'zip' ) >= 0 && b.indexOf( 'zip' ) >= 0 ) return true;
        // JPEG variants.
        if ( a.indexOf( 'jpeg' ) >= 0 && b.indexOf( 'jpeg' ) >= 0 ) return true;
        return false;
    }
})();
