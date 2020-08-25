// This is a custom payload script example
// Any .js file added to the /templates folder can be used as payload by adding /NAMEOFJSFILE to payload domain.
// Example {{payloadFile}}.js can be accessed by http://{{domain}}/{{payloadFile}}

alert('Custom script on ezXSS {{version}} with custom payload: {{payloadFile}}');