jQuery(document).ready(function ($) {
  // Fetch embed URL from backend
  $.get(DTA_FluxQS.embed_url_api, function (response) {
    if (response.success) {
      $('#flux-qs-dashboard-frame').attr('src', response.data.embedUrl);
    } else {
      console.error('Failed to get QuickSight embed URL:', response.data.message);
      $('#flux-qs-dashboard-frame').after('<p>Error loading dashboard.</p>');
    }
  });

  // Your existing location assignment logic can remain here
  $.get(DTA_FluxQS.api_url, function (data) {
    if (Array.isArray(data)) {
      let html = '<ul>';
      data.forEach(loc => {
        html += '<li><strong>' + loc.name + '</strong> (ID: ' + loc.id + ')</li>';
      });
      html += '</ul>';
      $('#location-assignment').html(html);
    } else {
      $('#location-assignment').html('<p>No locations found.</p>');
    }
  });
});
