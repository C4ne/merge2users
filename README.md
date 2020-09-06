## Terminology
`baseuser` = The user that will be preserved (May be also known as `new user`).   
`mergeuser` = The user that will be merged into the `baseuser` (May also be known as `new user`).

Note that it is heavily dependent on your merging settings which dataset of which user will be 
preserved. But the standard method is to preserve the data of the base user and delete the data
of the merge user if there is a conflict. 

## Define the merging logic for your plugin
Anything is done inside your `lib.php` file.
### Define settings
Almost every plugin needs to provide some sort of setting. To define settings for your plugin implement 
this method in your `lib.php` file:  
```php
/**
 * Delivers the settings to merge the datasets of two users for this plugin.
 * 
 * @param admin_settingpage $settingpage The settingpage of tool_merge2users
 */
function PLUGINTYPE_PLUGINNAME_deliver_merge_options(&$settingpage) {

}
```  
and add your settings to the `$settingpage` variable accordingly to your needs. Also note that this is a
"fake" settingpage. You will have to obtain the value of your settings from the `tool_merge2users` plugin.
See the section below for a clarification.

### Deliver merge queries
You are allowed to alter all the tables of your plugin. To do so implement this method in your `lib.php`:
```php
/**
 * Delivers the queries to merge the datasets of two users for all tables of this plugin.
 *
 * @param int $baseuserid The id of the base user
 * @param int $mergeuserid The id of the user to be merged
 * @return array An array of array of objects
 */
function PLUGINTYPE_PLUGINNAME_deliver_queries($baseuserid, $mergeuserid) {
    $somestrategy = get_config('tool_merge2users', 'PLUGINNAME_PLUGINTYPE_some_strategy');
    $queries = array();

    if ($somestrategy == 0) {
        $queryone = new stdClass();
        $queryone->sql = "UPDATE {tableone} SET userid=:baseuser WHERE userid=:mergeuser";
        $queryone->params = array('baseuser' => $baseuserid, 'mergeuser' => $mergeuserid);
        $queries['tableone'][] = $queryone;
    } else if ($somestrategy == 1) {
        $querytwo = new stdClass();
        $querytwo->sql = "DELETE FROM {tabletwo} WHERE userid=:mergeuser";
        $querytwo->params = array('mergeuser' => $mergeuserid);
        $queries['tabletwo'][] = $querytwo;
    } else {
        $mergerone = new \tool_merge2users\merger\generic_table_merger('tableone', $baseuserid, $mergeuserid, array('userid'));
        $mergertwo = new \tool_merge2users\merger\generic_table_merger('tabletwo', $baseuserid, $mergeuserid, array('userid'));
        
        $queries['tableone'] = $mergerone->get_queries();
        $queries['tabletwo'] = $mergertwo->get_queries();
    }

    return $queries;

}
```
Here you can also see that you will have to specify `tool_merge2users` to get the value of your original
setting. This is because all the merging settings are collected on the settings page of the 
`tool_merge2users` plugin.  
What you can also see is that you returned array needs to have a certain structure. The keys of the first
dimension must be the name of one of your tables (without the prefix) and have an array as its value. The
keys of the second dimension are important because thats the order in which the queries will be executed.
The values of the second dimension are standard objects. The object must have a `sql` member, which must
be a string and a `params` member which must be an array.  

If you do not do that the system will automatically merge all your plugin tables with the 
`generic_table_merger`.

### Figure out how to merge your datasets the right way
- Look at unique indexes from `get_indexes` 

TODO

## The generic table merger
