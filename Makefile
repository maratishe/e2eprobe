CC=gcc
FLAGS=-w -Wall
HEADERS=-I/usr/local/pfring/kernel -I/usr/local/pfring/userland/lib
LIBDIRS=-L/usr/local/pfring/userland/lib -L/usr/local/pfring/userland/libpcap-1.1.1-ring
LIBS=-lstdc++ -lc -lm
all: 
	$(CC) $(FLAGS)  -o probe.udp.proposed.tx probe.udp.proposed.tx.c $(LIBS)
	$(CC) $(FLAGS)  -o probe.udp.proposed.rx probe.udp.proposed.rx.c $(LIBS)
	$(CC) $(FLAGS)  -o probe.udp.igi.tx probe.udp.igi.tx.c $(LIBS)
	$(CC) $(FLAGS)  -o probe.udp.igi.rx probe.udp.igi.rx.c $(LIBS)
	$(CC) $(FLAGS)  -o probe.udp.pathchirp.tx probe.udp.pathchirp.tx.c $(LIBS)
	$(CC) $(FLAGS)  -o probe.udp.pathchirp.rx probe.udp.pathchirp.rx.c $(LIBS)
	