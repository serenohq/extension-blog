@if(isset($pinned))
    <h3>Featured Articles</h3>
    <p>
        @foreach($pinned as $post)
            <a href="@url($post['path'])">{{ $post['title'] }}</a><br>
        @endforeach
    </p>
@endif
