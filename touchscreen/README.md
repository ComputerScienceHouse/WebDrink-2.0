WebDrink: Touchscreen Edition!
===

A new WebDrink interface made especially for the Drink machine touchscreens!

config.js
---

```javascript
// Global configuration object
var CONFIG = (function() {
  var that = {};
  var ibutton = "iButton Value Goes Here";
  // Are we in development mode? (for testing)
  that.devMode = true;
  // Set a test iButton value (if in dev mode)
  that.deviButton = (that.devMode ? ibutton : false);
  // API config settings
  that.api = {
    // API base URL
    baseUrl: "https://webdrink.csh.rit.edu/api/"
  };
  // App config settings
  that.app = {
    // How long should the app wait for a drop to be started before logging out?
    sessionTimeout: 30000,
    // How long should the app wait to log out after a drink is dropped?
    dropTimeout: 3000
  };
  return that;
}());
```
