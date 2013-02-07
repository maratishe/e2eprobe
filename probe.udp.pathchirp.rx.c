#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <assert.h>
#include <unistd.h>
#include <float.h>
#include <math.h>
#include <stdarg.h>
#include <time.h>
#include <sys/time.h>
#include <sys/types.h>
#include <sys/socket.h>
#include <netinet/in.h>
#include <arpa/inet.h>
#include <unistd.h>// add 2 lines
#include <fcntl.h>
#include <sys/stat.h>


FILE *out;
double getime() {
	struct timeval tv;
	gettimeofday( &tv, NULL);
	return ( double)( tv.tv_sec + ( ( double) tv.tv_usec) / 1000000.0);
}
void flog( const char *file, const char *msg) {
	FILE *out = fopen( file, "a");
	double time = getime();
	fprintf( out, "times=%d,timeus=%d,%s\n", ( int)time, ( int)( 1000000 * ( time - ( int)time)), msg);
	fclose( out);
}
void flog2( FILE *file, const char *msg) {
	double time = getime();
	fprintf( file, "times=%d,timeus=%d,%s\n", ( int)time, ( int)( 1000000 * ( time - ( int)time)), msg);
}
int main( int argc, char **argv) {
	int status, limit, i;
	if ( argc != 5) {  printf( "bg.udp.rx [1 port][2 file][3 psize][4 probesize]\n"); exit( 1); }
	int port = atoi( argv[ 1]);
	out = fopen( argv[ 2], "w");
	int psize = atoi( argv[ 3]);
	int probesize = atoi( argv[ 4]);
	setbuf( stdout, NULL);	// print immediately, without buffering
	
	// create and bind to an incoming socket
	struct sockaddr_in addrin;		// the address in
	addrin.sin_family = AF_INET;
	addrin.sin_port = htons( port);
	addrin.sin_addr.s_addr = INADDR_ANY;	// fill it with my IP
	int sock = socket( AF_INET, SOCK_DGRAM, 0);
	status = -1; limit = 10;
	while ( status < 1 && limit--) status = bind( sock, ( struct sockaddr *)&addrin, sizeof( struct sockaddr));
	if ( ! status) { printf( "error binding to port[%d]\n", port); return 1; }
	// make socket non-blocking
	int flags = fcntl( sock, F_GETFL); //modif 3 lines from here
	flags |= O_NONBLOCK;
	fcntl( sock, F_SETFL, flags);
	
	// wait for startfile to appear
	//struct stat finfo; srand( time( NULL));
	//while ( stat( startfile, &finfo) != 0) usleep( 50000 + rand() % 50000);
	//flog2( out, "type=start");
	
	int one, two, three;	// packet id, txtime.s, txtime.us (remote)
	double time, start; start = getime();	// for duration timeout
	char buf[ 10000]; for ( i = 0; i < 2000; i++) buf[ i] = '\0';
	int data[ 20000]; for ( i = 0; i < 20000; i++) data[ i] = -1;
	int pos = 0; double now = getime(); 	// for writing data
	double lastime = -1;
	while ( 1) { // while lock file is on
		now = getime();
		int status = recvfrom( sock, buf, psize, 0, NULL, 0);	// first packet is 100 bytes
		if ( lastime != -1 && now - lastime > 3) break;	// timeout
		if ( pos >= probesize  - 1) break;
		if ( status != psize) continue;
		sscanf( buf, "%d", &one); // packet id
		if ( one > probesize) continue;
		if ( lastime == -1) lastime = now;
		data[ one] = ( int)( 1000000.0 * ( now - lastime));
		pos = one; lastime = now;
	}
	// done, write data and exit
	fprintf( out, "probesize=%d,psize=%d\n", probesize, psize);
	for ( i = 0; i < pos; i++) fprintf( out, "pos=%d,pspace=%d\n", i, data[ i]);
	fclose( out);
	close( sock);	// close the socket
}




