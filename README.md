#MongoDB based session Handler#

For Kohana 3

Just a prototype at the moment, and definitely looking for suggestions!

##Some of the things that still need to be implemented over from the default session:
*Encrypting the session using the native Kohana encryption class.  Should encrypt each key separately

##Some of the mongo specific things that need to be implement
*Make connections an array that can take multiple hosts for failover
*Add an option to save sessions as base64 encoded strings to GridFS

Currently the handler saves the session data as an embedded element of key value pairs wrapped with a key that can be set using the 'contents_key' configuration option

