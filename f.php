< ?php >
c=file_get_contents( " /var/www/html/dashboard/index.html " ) ;
c=str_replace( " var appIcons = { " , " function getPortIcon(dport, proto){var i={53:DNS,80:HTTP,443:HTTPS,22:SSH};return i[dport]?i[dport]:proto.toUpperCase().concat(chr(47)).concat(dport);} var appIcons = { " , c) ;
c=str_replace( " var proto = sess.proto; " , " var pd=getPortIcon(sess.dport,sess.proto); " , c) ;
c=str_replace( " + proto + " , " + pd + " , c) ;
file_put_contents( " /var/www/html/dashboard/index.html " , c) ;
echo " Done " ;
