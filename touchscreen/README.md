WebDrink: Touchscreen Edition!
===

A new WebDrink interface made especially for the Drink machine touchscreens!

config.js
---

```javascript
var CONFIG = {
  devMode: true, // true or false
  devIbutton: "", // dev ibutton value goes here
  api: {
    baseUrl: "https://webdrink.csh.rit.edu/api/"
  },
  app: {
    sessionTimeout: 15000
  }
};
```
