// This is a custom payload script example.
// Any .js file added to the /payload/custom folder can be served as a named
// payload by requesting /NAMEOFJSFILE on the payload domain.
// Example: {{fileName}}.js is reachable at {{protocol}}://{{domain}}/{{fileName}}
//
// Supported placeholders: {{version}} {{domain}} {{protocol}} {{fileName}}

alert('Custom script on ezXSS {{version}} with custom payload: {{fileName}}');
