/**
 * Fix for [object Object] display in DebugBar VariableListWidget
 * This overrides the default itemRenderer to properly handle objects
 */
(function() {
    // Wait for PhpDebugBar to be loaded
    if (typeof PhpDebugBar === 'undefined' || !PhpDebugBar.Widgets || !PhpDebugBar.Widgets.VariableListWidget) {
        // If not loaded yet, wait and try again
        setTimeout(arguments.callee, 100);
        return;
    }
    
    // Store original VariableListWidget
    var OriginalVariableListWidget = PhpDebugBar.Widgets.VariableListWidget;
    
    // Override with fixed version
    PhpDebugBar.Widgets.VariableListWidget = OriginalVariableListWidget.extend({
        itemRenderer: function(dt, dd, key, value) {
            // Add key to dt element
            $('<span />').attr('title', key).text(key).appendTo(dt);
            
            // Handle the value display
            var displayValue = value;
            
            // If value has a .value property, use that
            if (value && typeof value === 'object' && 'value' in value) {
                displayValue = value.value;
            }
            
            // Convert objects/arrays to string representation
            if (typeof displayValue === 'object' && displayValue !== null) {
                try {
                    displayValue = JSON.stringify(displayValue, null, 2);
                } catch (e) {
                    displayValue = String(displayValue);
                }
            }
            
            // Ensure we have a string
            var textValue = String(displayValue);
            
            // Truncate long values
            if (textValue.length > 100) {
                textValue = textValue.substr(0, 100) + "...";
            }
            
            // Set up click handler for pretty display
            var prettyVal = null;
            dd.text(textValue).click(function() {
                if (dd.hasClass(PhpDebugBar.utils.makecsscls('phpdebugbar-widgets-')('pretty'))) {
                    dd.text(textValue).removeClass(PhpDebugBar.utils.makecsscls('phpdebugbar-widgets-')('pretty'));
                } else {
                    // For pretty display, use original value
                    prettyVal = prettyVal || PhpDebugBar.Widgets.createCodeBlock(
                        typeof value === 'object' ? JSON.stringify(value, null, 2) : String(value)
                    );
                    dd.addClass(PhpDebugBar.utils.makecsscls('phpdebugbar-widgets-')('pretty')).empty().append(prettyVal);
                }
            });
        }
    });
})();