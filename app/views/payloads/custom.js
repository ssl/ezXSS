// This is a custom payload script example
// Any .js file added to the /app/views/payloads folder can be used as payload by adding /NAMEOFJSFILE to payload domain.
// Example {{fileName}}.js can be accessed by https://{{domain}}/{{fileName}}

alert('Custom script on ezXSS {{version}} with custom payload: {{fileName}}');