JobQueue Architecture {#jobqueuearch}
=====================
Notes on the Job queuing system architecture.

## Introduction

The data model consist of the following main components:
* The Job object represents a particular deferred task that happens in the
  background. All jobs subclass the Job object and put the main logic in the
  function called run().
* The JobQueue object represents a particular queue of jobs of a certain type.
  For example there may be a queue for email jobs and a queue for CDN purge
  jobs.

## Job queues

Each job type has its own queue and is associated to a storage medium. One
queue might save its jobs in redis while another one uses would use a database.

Storage medium are defined in a queue class. Before using it, you must
define in $wgJobTypeConf a mapping of the job type to a queue class.

The factory class JobQueueGroup provides helper functions:
- getting the queue for a given job
- route new job insertions to the proper queue

The following queue classes are available:
* JobQueueDB (stores jobs in the `job` table in a database)
* JobQueueRedis (stores jobs in a redis server)

All queue classes support some basic operations (though some may be no-ops):
* enqueueing a batch of jobs
* dequeueing a single job
* acknowledging a job is completed
* checking if the queue is empty

Some queue classes (like JobQueueDB) may dequeue jobs in random order while other
queues might dequeue jobs in exact FIFO order. Callers should thus not assume jobs
are executed in FIFO order.

Also note that not all queue classes will have the same reliability guarantees.
In-memory queues may lose data when restarted depending on snapshot and journal
settings (including journal fsync() frequency).  Some queue types may totally remove
jobs when dequeued while leaving the ack() function as a no-op; if a job is
dequeued by a job runner, which crashes before completion, the job will be
lost. Some jobs, like purging CDN caches after a template change, may not
require durable queues, whereas other jobs might be more important.

## Job queue aggregator

Since each job type has its own queue, and wiki-farms may have many wikis,
there might be a large number of queues to keep track of. To avoid wasting
large amounts of time polling empty queues, aggregators exists to keep track
of which queues are ready.

The following queue aggregator classes are available:
* JobQueueAggregatorRedis (uses a redis server to track ready queues)

Some aggregators cache data for a few minutes while others may be always up to date.
This can be an important factor for jobs that need a low pickup time (or latency).

## Jobs

Callers should also try to make jobs maintain correctness when executed twice.
This is useful for queues that actually implement ack(), since they may recycle
dequeued but un-acknowledged jobs back into the queue to be attempted again. If
a runner dequeues a job, runs it, but then crashes before calling ack(), the
job may be returned to the queue and run a second time. Jobs like cache purging can
happen several times without any correctness problems. However, a pathological case
would be if a bug causes the problem to systematically keep repeating. For example,
a job may always throw a DB error at the end of run(). This problem is trickier to
solve and more obnoxious for things like email jobs, for example. For such jobs,
it might be useful to use a queue that does not retry jobs.
