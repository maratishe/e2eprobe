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
#include <unistd.h>
#include <fcntl.h>
#include <sys/ioctl.h>
#include <sys/stat.h>


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
	if ( argc != 5) { printf( "bg.udp.tx [1 rip][2 rport][3 psize][4 probesize]\n"); exit( 1); }
	char *rip = argv [ 1];
	int rport = atoi( argv[ 2]);
	int psize = atoi( argv[ 3]);
	int probesize = atoi( argv[ 4]);	// usec
	
	
	struct sockaddr_in addrout;	// the address out
	addrout.sin_family = AF_INET;
	addrout.sin_port = htons( rport);
	inet_aton( rip, &addrout.sin_addr);
	int sock = socket( AF_INET, SOCK_DGRAM, 0);
	int flags = fcntl( sock, F_GETFL); //modif 3 lines from here
	flags |= O_NONBLOCK;
	fcntl( sock, F_SETFL, flags);
	
	
	// wait for startfile to appear
	//struct stat finfo; srand( time( NULL));
	//while ( stat( startfile, &finfo) != 0) usleep( 50000 + rand() % 50000);
	//flog2( out, "type=start");
	
	
	int id = 0; limit = 0;
	char buf[ psize + 2]; for ( i = 0; i < psize + 2; i++) buf[ i] = '\0';
	double time, ltime, start; start = getime(); ltime = start;
	char msg[ 128];
	while ( probesize-- > 0) {
		sprintf( buf, "%d", id, 0, 0);
		status = sendto( sock, buf, psize, 0, ( struct sockaddr *)&addrout, sizeof( struct sockaddr_in));
		ltime = time; id++;
	}
	sleep( 1);
	close( sock);
	
}

