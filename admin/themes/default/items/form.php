<?php echo js('tooltip'); 
echo js('tiny_mce/tiny_mce');
?>
<script type="text/javascript" charset="utf-8">
//<![CDATA[

	Event.observe(window,'load', function() {
        Omeka.ItemForm.enableTagRemoval();
        
        document.fire('omeka:elementformload');
        
        // Reset the IDs of the textareas so as to not confuse the WYSIWYG enabler buttons.
        $$('div.field textarea').each(function(el){
            el.id = null;
        });
        
		Omeka.ItemForm.enableAddFiles();
        Omeka.ItemForm.changeItemType();
	});
    
    document.observe('omeka:elementformload', function(e){
        var elementFieldDiv = e.target;
        Omeka.ItemForm.makeElementControls();
        Omeka.ItemForm.enableWysiwyg();
    });
    
    Omeka.ItemForm = Object.extend(Omeka.ItemForm || {}, {

    changeItemType: function() {
		$('change_type').hide();
		$('item-type').onchange = function() {
			var typeSelectLabel = $$('#type-select label')[0];
			var image = $(document.createElement('img'));
			image.src = "<?php echo img('loader2.gif'); ?>";
			var params = 'item_id=<?php echo $item->id; ?>&type_id='+this.getValue();
						
			new Ajax.Request('<?php echo uri("items/change-type") ?>', {
				parameters: params,
				onCreate: function(t) {
					typeSelectLabel.appendChild(image);
				},
				onFailure: function(t) {
					alert(t.status);
				},
				onComplete: function(t) {
					var form = $('type-metadata-form');
					image.remove();
					form.update(t.responseText);
					form.fire('omeka:elementformload');
					Effect.BlindDown(form);
				}
			});
		}        
    },
	
	/* Messing with the tag list should not submit the form.  Instead it runs 
	an AJAX request to remove tags. */
	enableTagRemoval: function() {		
		if ( !(buttons = $$('#tags-list input')) ) {
		    return;
		}

    	function removeTag(button) {
    		var tagId = parseInt(button.value);
    		var uri = "<?php echo uri('items/edit/'.$item->id); ?>";

    		new Ajax.Request("<?php echo uri('items/edit/'.$item->id); ?>", {
    			parameters: 'remove_tag='+ tagId,
    			method: 'post',
    			onSuccess: function(t) {
    				//Fire the other ajax request to update the page
    				new Ajax.Updater('tag-form', "<?php echo uri('items/tag-form/'); ?>", {
    					parameters: {
    						'id': "<?php echo $item->id; ?>"
    					},
    					onComplete: function() {
    					    Effect.Appear('tag-form', {duration: 1.0});
    						Omeka.ItemForm.enableTagRemoval();
    					}
    				});
    			},
    			onFailure: function(t) {
    				alert(t.status);
    			}
    		});

    		return false;
    	}
		
		buttons.invoke('observe', 'click', function(e) {
		    e.stop();
		    removeTag(this);
		});
	},
	
	/**
	 * Send an AJAX request to update a <div class="field"> that contains all 
	 * the form inputs for an element.
	 */
	elementFormRequest: function(fieldDiv, params) {
        var elementId = fieldDiv.id.gsub(/element-/, '');
        
        // Isolate the inputs on this part of the form.
        var inputs = fieldDiv.select('input', 'textarea', 'select');
        var postString = inputs.invoke('serialize').join('&');        
        params.element_id = elementId;

        // note to yourself that this php is hard coded into the javascript
        // you cannot move this javascript to another file even if you wanted to
        var elementFormPartialUri = "<?php echo uri('items/element-form'); ?>";
        params.item_id = "<?php echo item('id'); ?>";
        
        // Make sure that we put in that we want to add one input to the form
        postString += '&' + $H(params).toQueryString();
        
        new Ajax.Updater(fieldDiv, elementFormPartialUri, {
            parameters: postString,
            onComplete: function(t) {
                fieldDiv.fire('omeka:elementformload');
            }
        });
	},
	
	addElementControl: function(e){
        e.stop();
        var addButton = Event.element(e);
        var fieldDiv = addButton.up('div.field');
        Omeka.ItemForm.elementFormRequest(fieldDiv, {add:'1'});
    },
    
    deleteElementControl: function(e){
        e.stop();
        
        var removeButton = Event.element(e);

        //Check if there is only one input.
        var inputCount = removeButton.up('div.field').select('div.input-block').size();
        if (inputCount == 1) {
            return;
        };

        if(!confirm('Do you want to delete this?')) {
            return;
        }
                
        removeButton.up('div.input-block').remove();
	},
    
    makeElementControls: function() {
 	    // Class name is hard coded here b/c it is hard coded in the helper
	    // function as well.
	    $$('.add-element').invoke('observe', 'click', Omeka.ItemForm.addElementControl);
	    
	    // When button is clicked, remove the last input that was added
	    $$('.remove-element').invoke('observe', 'click', Omeka.ItemForm.deleteElementControl);
	},
	
	/**
	 * Adds an arbitrary number of file input elements to the items form so that
     * more than one file can be uploaded at once.
	 */
    enableAddFiles: function() {
        if(!$('add-more-files')) return;
        if(!$('file-inputs')) return;
        if(!$$('#file-inputs .files')) return;
        var nonJsFormDiv = $('add-more-files');

        //This is where we put the new file inputs
        var filesDiv = $$('#file-inputs .files').first();

        var filesDivWrap = $('file-inputs');
        //Make a link that will add another file input to the page
        var link = $(document.createElement('a')).writeAttribute(
            {href:'#',id: 'add-file', className: 'add-file'}).update('Add Another File');

        Event.observe(link, 'click', function(e){
            e.stop();
            var inputs = $A(filesDiv.getElementsByTagName('input'));
            var inputCount = inputs.length;
            var fileHtml = '<div id="fileinput'+inputCount+'" class="fileinput"><input name="file['+inputCount+']" id="file['+inputCount+']" type="file" class="fileinput" /></div>';
            new Insertion.After(inputs.last(), fileHtml);
            $('fileinput'+inputCount).hide();
            new Effect.SlideDown('fileinput'+inputCount,{duration:0.2});
            //new Effect.Highlight('file['+inputCount+']');
        });

        nonJsFormDiv.update();
		
        filesDivWrap.appendChild(link);
    },
	
	enableWysiwygCheckbox: function(checkbox) {
	    
	    function getTextarea(checkbox) {
	        var textarea = checkbox.up('.input-block').down('textarea', 0);
            // We can't use the editor for any field that isn't a textarea
            if (Object.isUndefined(textarea)) {
                return;
            };
            
            textarea.identify();
            return textarea;
	    }
	    
	    // Add the 'html-editor' class to all textareas that are flagged as HTML.
	    var textarea = getTextarea(checkbox);
	    if (checkbox.checked && textarea) {
            textarea.addClassName('html-editor');
	    };
	            
        // Whenever the checkbox is toggled, toggle the WYSIWYG editor.
        Event.observe(checkbox, 'click', function(e) {
            var textarea = getTextarea(checkbox);
            
            if (textarea) {
                // Toggle on when checked.
                if(checkbox.checked) {
                   tinyMCE.execCommand("mceAddControl", false, textarea.id);               
                } else {
                  tinyMCE.execCommand("mceRemoveControl", false, textarea.id);
                }                
            };
        });
	},
	
	/**
	 * Make it so that checking a box will enable the WYSIWYG HTML editor for
     * any given element field. This can be enabled on a per-textarea basis as
     * opposed to all fields for the element.
	 */
	enableWysiwyg: function() {
	    
	    $$('div.inputs').each(function(div){
            // Get all the WYSIWYG checkboxes
            var checkboxes = div.select('input[type="checkbox"]');
            checkboxes.each(Omeka.ItemForm.enableWysiwygCheckbox);
	    });
    
	    //WYSIWYG Editor
       tinyMCE.init({
        mode: "specific_textareas",
        editor_selector : "html-editor",    // Put the editor in for all textareas with an 'html-editor' class.
       	theme: "advanced",
       	theme_advanced_toolbar_location : "top",
       	theme_advanced_buttons1 : "bold,italic,underline,justifyleft,justifycenter,justifyright,bullist,numlist,link,formatselect",
		theme_advanced_buttons2 : "",
		theme_advanced_buttons3 : "",
		theme_advanced_toolbar_align : "left"
       });   
	}

    });
//]]>	
</script>

<?php echo flash(); ?>

<div id="public-featured">
    <?php if ( has_permission('Items', 'makePublic') ): ?>
    	<div class="checkbox">
    		<label for="public">Public:</label> 
    		<div class="checkbox"><?php echo checkbox(array('name'=>'public', 'id'=>'public'), $item->public); ?></div>
    	</div>
    <?php endif; ?>
    <?php if ( has_permission('Items', 'makeFeatured') ): ?>
    	<div class="checkbox">
    		<label for="featured">Featured:</label> 
    		<div class="checkbox"><?php echo checkbox(array('name'=>'featured', 'id'=>'featured'), $item->featured); ?></div>
    	</div>
    <?php endif; ?>
</div>
<div id="item-metadata">
<?php foreach ($elementSets as $key => $elementSet): ?>
	<div id="<?php echo text_to_id($elementSet->name); ?>-metadata">
<fieldset class="set">
        <legend><?php echo htmlentities($elementSet->name); ?></legend>
        <?php 
        // Would prefer to display all the metadata sets in the same way, but this
        // necessitates branching for the Item Type set.
        switch ($elementSet->name):
            case 'Item Type Metadata':
                include 'item-type-form.php';
                break;
            default:
                echo display_element_set_form_for_item($item, $elementSet->name);
                break;
        endswitch; ?>        
</fieldset>
</div>
<?php endforeach; ?>

<!-- Create the tabs for the rest of the form -->
<?php 
// Each one of these tabs is a partial file for purposes of clean separation.
$otherTabs = array('Collection', 'Files', 'Tags', 'Miscellaneous'); ?>
<?php foreach ($otherTabs as $tabName): ?>
    <div class="set" id="<?php echo text_to_id($tabName);?>-metadata">
	<fieldset>
        <legend><?php echo $tabName; ?></legend>
    <?php switch ($tabName): 
            case 'Collection':
                require 'collection-form.php';
            break;
            case 'Files':
                require 'files-form.php';
            break;
            case 'Tags':
                require 'tag-form.php';
            break;
            case 'Miscellaneous':
                require 'miscellaneous-form.php';
            break; 
        endswitch; ?>
</fieldset>
    </div>
<?php endforeach; ?>
</div>