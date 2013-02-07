
This source code containds C/C++ implementation of three end-to-end (e2e) available bandwidth measurement tools, namely PathChirp, IGI, and yet another one (proposed). 

The main idea of the proposed tool is to use entropy (permutation entropy) to estimate complexity of time series produced by the probing, which can then help estimate e2e available bandwidth. 

The logic of the code:
(1) make is resilient to failure.  Both tx* and rx* parts are written in tx.run* and rx.run* scripts, which restart their children for each loop.
(2) the probing itself is done in binary code (compiled C/C++). 
(3) Ports are simple.  A given port X is used for communication over PHP sockets, X+1 is used for probing.
(4) Logs are written at server (receiver size), which means that client supplies additional informaiton to be written to the log file.


======
Installation 
======

(1) In your environment, Cygwin or Linux, create /web directory and put ajaxkit folder into it
(2) make sure you compile the binary version of tools:   > make
(3) make sure you have atd service turned on:   > service atd status
(4) make sure you have PHP5 in your environment
       - with sockets module turned on


       
=========
Configuration and structure
=========
Basically, all configuration is done in tx.php, which is called by tx.run.php. 
You can configure packet size and probesize. 

Secondary configuration is solid inside PathChirp and IGI code.
- exponential pspace sequence -- PathChirp
- number probes with different packet space -- IGI


========
Execution 
========
* see commandline.txt for command line examples
(1) run rx.run.php first
(2) run tx.run.php
(3) see output of tx.run.php, but beware that stats are collected at RX side as [tag].bz64jsonl
      - bz64jsonl is for base64( bzip( json)) datatype, written as one chunk per line
      - see ajaxkit/lib/json.php for reference 
      - see ajaxkit/lib/file.php  for functions which help you read such files: fout* fin* functions



