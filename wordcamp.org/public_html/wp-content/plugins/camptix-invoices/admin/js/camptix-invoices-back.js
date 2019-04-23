jQuery(document).ready(function($) {
  var file_frame;
  $('.camptix-media').on('click', 'input[data-set]', function(e) {
    e.preventDefault();
    var $field = $(this).parent('.camptix-media').find('input[data-field]').eq(0);
    var $imageWrapper = $(this).parent('.camptix-media').find('div[data-imagewrapper]').eq(0);
    var $delete = $(this).parent('.camptix-media').find('input[data-unset]').eq(0);
    var set_to_post_id = $field.val();
    if (file_frame) {
      file_frame.open();
      return;
    }
    file_frame = wp.media.frames.file_frame = wp.media({
      title: camptixInvoiceBackVars.selectText,
      button: {
        text: camptixInvoiceBackVars.selectImage,
      },
      multiple: false,
      library: {
        type: 'image',
      }
    });
    file_frame.on('select', function () {
      attachment = file_frame.state().get('selection').first().toJSON();
      $imageWrapper.html('<img src="' + attachment.sizes.thumbnail.url + '" width="' + attachment.sizes.thumbnail.width + '"/>' );
      $field.val(attachment.id);
      $delete.show();
    });
    file_frame.on('open',function() {
      var selection = file_frame.state().get('selection');
      attachment = wp.media.attachment(set_to_post_id);
      attachment.fetch();
      selection.add(attachment ? [attachment] : []);
    });
    file_frame.open();
  });

  $('.camptix-media').on('click', 'input[data-unset]', function(e) {
    e.preventDefault();
    var $field = $(this).parent('.camptix-media').find('input[data-field]').eq(0);
    var $imageWrapper = $(this).parent('.camptix-media').find('div[data-imagewrapper]').eq(0);
    $field.val('');
    $imageWrapper.html('');
    $(this).hide();
  });
});
