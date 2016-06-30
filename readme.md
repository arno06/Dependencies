Dependencies
============

* Setup JS Libraries in manifest.json
* Define only one script tag in order to load many JS libs
* Handle dependencies
* Handle local and remote files
* Compress result
* Handle cache (with duration setup in manifest.json)

Usage
------------
```html
<script src="path/to/Dependencies/src/?need=Lib1,Lib2,M4,Stage"></script>
```

Note
------------
This version only work with JS but CSS files could be targeted with some changes ([see php-fw repo](https://github.com/arno06/php-fw))

Todo
-----------
 * [ ] Add Minified support

[![SensioLabsInsight](https://insight.sensiolabs.com/projects/e1151727-820e-4603-a352-dd6e5fc96bd2/mini.png)](https://insight.sensiolabs.com/projects/e1151727-820e-4603-a352-dd6e5fc96bd2)