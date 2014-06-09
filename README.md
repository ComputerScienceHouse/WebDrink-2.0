# WebDrink 2.0

Rewrite of CSH WebDrink. Includes updated interface and new API. 

Based on the original [WebDrink](https://github.com/ComputerScienceHouse/WebDrink). Uses [Drink-JS](https://github.com/ComputerScienceHouse/Drink-JS) to communicate with the Drink hardware.

CSH-Public Demo: https://members.csh.rit.edu/~bencentra/webdrink/

Check out the [wiki](https://github.com/bencentra/WebDrink-2.0/wiki) for API docs and more.

__Why?__
* Address usability concerns (make it mobile friendly, improve admin experience, etc)    
* Make improved API for current/future development (mobile apps, new new webdrink, etc)
* Learning, I guess (Angular, RESTful API, etc)

__It Uses:__
* [Twitter Bootstrap](http://getbootstrap.com/)    
* [AngularJS](http://angularjs.org/)    
* [A RESTful API](http://coreymaynard.com/blog/creating-a-restful-api-with-php/)    
* Boring ol' PHP        

Let me know (via email or GitHub issue) of any feature requests or glaring implementation/security issues.

### What happened to UUIDs?

Due to difficulties upgrading [Drink-JS](https://github.com/ComputerScienceHouse/Drink-JS), the integration of UUID-based changes is on hold. Despite its issues Drink-JS does remain functional, so WebDrink-2.0 should be able to run on the current configuration. 

If Drink-JS should be upgraded, or if it should be replaced by a new Drink server, there are several options for updating WebDrink-2.0:
* Replace the websockets stuff in `app.js` with logic for talking to the new server.    
* Add API methods to `drink_api.php` that talk directly to the new server and add the appropriate calls to the services in `app.js`.    